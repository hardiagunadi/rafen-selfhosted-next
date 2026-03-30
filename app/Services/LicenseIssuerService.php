<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class LicenseIssuerService
{
    private const FALLBACK_PRESETS = [
        'starter' => [
            'label' => 'Starter',
            'description' => 'Paket dasar untuk deployment kecil dengan fitur inti dan batas perangkat ringan.',
            'modules' => ['core', 'mikrotik'],
            'limits' => [
                'max_mikrotik' => 3,
                'max_ppp_users' => 200,
            ],
            'grace_days' => 21,
        ],
        'growth' => [
            'label' => 'Growth',
            'description' => 'Paket menengah untuk ISP yang butuh otomasi operasional harian.',
            'modules' => ['core', 'mikrotik', 'radius', 'vpn', 'wa'],
            'limits' => [
                'max_mikrotik' => 10,
                'max_ppp_users' => 1000,
            ],
            'grace_days' => 21,
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'description' => 'Paket lengkap untuk deployment besar dengan stack modul penuh.',
            'modules' => ['core', 'mikrotik', 'radius', 'vpn', 'wa', 'olt', 'genieacs'],
            'limits' => [
                'max_mikrotik' => 30,
                'max_ppp_users' => 5000,
            ],
            'grace_days' => 30,
        ],
    ];

    public function __construct(
        private readonly LicenseSignatureService $licenseSignatureService,
    ) {}

    /**
     * @param  array<int, string>  $allowedHosts
     * @param  array<int, string>  $modules
     * @param  array<string, int|string>  $limits
     * @return array<string, mixed>
     */
    public function issue(
        string $customerName,
        string $instanceName,
        string $fingerprint,
        string $expiresAt,
        array $allowedHosts = [],
        array $modules = [],
        array $limits = [],
        ?string $licenseId = null,
        ?string $issuedAt = null,
        ?string $supportUntil = null,
        ?int $graceDays = null,
        ?string $accessMode = null,
    ): array {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('Ekstensi sodium wajib aktif untuk menandatangani lisensi.');
        }

        $secretKey = $this->secretKey();

        $normalizedHosts = $this->normalizeAllowedHosts($allowedHosts);

        $payload = [
            'license_id' => $licenseId ?: $this->generateLicenseId(),
            'customer_name' => trim($customerName),
            'instance_name' => trim($instanceName),
            'issued_at' => $this->normalizeDate($issuedAt ?? now()->toDateString(), 'issued_at'),
            'expires_at' => $this->normalizeDate($expiresAt, 'expires_at'),
            'support_until' => $supportUntil !== null && $supportUntil !== '' ? $this->normalizeDate($supportUntil, 'support_until') : null,
            'grace_days' => $graceDays ?? (int) config('license.default_grace_days', 21),
            'fingerprint' => trim($fingerprint),
            'domains' => $normalizedHosts,
            'allowed_hosts' => $normalizedHosts,
            'access_mode' => $this->normalizeAccessMode($accessMode, $normalizedHosts),
            'modules' => $this->normalizeModules($modules),
            'limits' => $limits,
        ];

        $payload['signature'] = base64_encode(sodium_crypto_sign_detached(
            $this->licenseSignatureService->canonicalize($payload),
            $secretKey,
        ));

        return $payload;
    }

    /**
     * @param  array<int, string>  $allowedHosts
     * @return list<string>
     */
    private function normalizeAllowedHosts(array $allowedHosts): array
    {
        $normalizedHosts = [];

        foreach ($allowedHosts as $host) {
            $normalizedHost = trim(Str::lower($host));

            if ($normalizedHost === '') {
                continue;
            }

            $normalizedHosts[$normalizedHost] = $normalizedHost;
        }

        return array_values($normalizedHosts);
    }

    /**
     * @param  list<string>  $allowedHosts
     */
    private function normalizeAccessMode(?string $accessMode, array $allowedHosts): string
    {
        $normalizedAccessMode = trim(Str::lower((string) $accessMode));

        if (in_array($normalizedAccessMode, ['fingerprint_only', 'ip_based', 'domain_based', 'hybrid'], true)) {
            return $normalizedAccessMode;
        }

        if ($allowedHosts === []) {
            return 'fingerprint_only';
        }

        $hasIpHost = collect($allowedHosts)->contains(fn (string $host): bool => filter_var($host, FILTER_VALIDATE_IP) !== false);
        $hasDomainHost = collect($allowedHosts)->contains(fn (string $host): bool => filter_var($host, FILTER_VALIDATE_IP) === false);

        return match (true) {
            $hasIpHost && $hasDomainHost => 'hybrid',
            $hasIpHost => 'ip_based',
            default => 'domain_based',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        $privateKeyPath = (string) config('license.private_key_path');
        $error = null;

        try {
            $this->secretKey();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return [
            'private_key_path' => $privateKeyPath,
            'has_private_key' => $privateKeyPath !== '' && File::exists($privateKeyPath),
            'is_ready' => $error === null,
            'error' => $error,
            'presets' => $this->presets(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function presets(): array
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($plans->isEmpty()) {
            return self::FALLBACK_PRESETS;
        }

        $presets = [];

        foreach ($plans as $plan) {
            $presetKey = $this->presetKey($plan);

            $presets[$presetKey] = [
                'label' => $plan->name,
                'description' => $plan->description ?: 'Preset dari paket langganan aktif.',
                'modules' => $this->modulesFromPlan($plan),
                'limits' => $this->limitsFromPlan($plan),
                'grace_days' => (int) config('license.default_grace_days', 21),
            ];
        }

        return $presets;
    }

    /**
     * @return list<string>
     */
    public function presetKeys(): array
    {
        return array_keys($this->presets());
    }

    private function secretKey(): string
    {
        $privateKeyPath = (string) config('license.private_key_path');

        if ($privateKeyPath === '' || ! File::exists($privateKeyPath)) {
            throw new RuntimeException('Private key issuer lisensi belum tersedia di server SaaS.');
        }

        $encodedSecretKey = trim((string) File::get($privateKeyPath));
        $decodedSecretKey = base64_decode($encodedSecretKey, true);
        $expectedLength = defined('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES') ? SODIUM_CRYPTO_SIGN_SECRETKEYBYTES : 64;

        if ($decodedSecretKey === false || strlen($decodedSecretKey) !== $expectedLength) {
            throw new RuntimeException('Private key issuer lisensi tidak valid.');
        }

        $configuredPublicKey = (string) config('license.public_key');
        $derivedPublicKey = base64_encode(sodium_crypto_sign_publickey_from_secretkey($decodedSecretKey));

        if ($configuredPublicKey === '') {
            throw new RuntimeException('LICENSE_PUBLIC_KEY belum diatur di environment aplikasi.');
        }

        if (! hash_equals($configuredPublicKey, $derivedPublicKey)) {
            throw new RuntimeException('Private key issuer tidak cocok dengan LICENSE_PUBLIC_KEY pada server SaaS ini.');
        }

        return $decodedSecretKey;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<int, string>
     */
    private function normalizeModules(array $modules): array
    {
        $normalizedModules = array_values(array_unique(array_filter(
            array_map('trim', $modules),
            fn (string $module): bool => $module !== ''
        )));

        if ($normalizedModules === []) {
            return ['core'];
        }

        if (! in_array('core', $normalizedModules, true)) {
            $normalizedModules[] = 'core';
        }

        return array_values($normalizedModules);
    }

    private function normalizeDate(string $value, string $field): string
    {
        $date = Carbon::createFromFormat('Y-m-d', trim($value));

        if (! $date || $date->format('Y-m-d') !== trim($value)) {
            throw new RuntimeException("Tanggal {$field} harus berformat YYYY-MM-DD.");
        }

        return $date->toDateString();
    }

    private function generateLicenseId(): string
    {
        return 'RAFEN-SH-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    private function presetKey(SubscriptionPlan $plan): string
    {
        $baseKey = Str::slug((string) ($plan->slug ?: $plan->name), '_');

        return $baseKey !== '' ? $baseKey : 'plan_'.$plan->id;
    }

    /**
     * @return list<string>
     */
    private function modulesFromPlan(SubscriptionPlan $plan): array
    {
        $modules = ['core'];

        if ($plan->max_mikrotik !== 0) {
            $modules[] = 'mikrotik';
        }

        $features = collect($plan->features ?? [])
            ->filter(fn (mixed $feature): bool => is_string($feature) && $feature !== '')
            ->map(fn (string $feature): string => Str::lower($feature))
            ->values();

        $featureModuleMap = [
            'radius' => ['radius integration', 'freeradius integration', 'radius'],
            'vpn' => ['vpn', 'vpn access', 'vpn dedicated', 'vpn tunnel'],
            'wa' => ['whatsapp', 'whatsapp integration', 'wa'],
            'olt' => ['olt', 'olt integration'],
            'genieacs' => ['genieacs', 'cpe'],
        ];

        foreach ($featureModuleMap as $module => $keywords) {
            if ($features->contains(function (string $feature) use ($keywords): bool {
                foreach ($keywords as $keyword) {
                    if (str_contains($feature, $keyword)) {
                        return true;
                    }
                }

                return false;
            })) {
                $modules[] = $module;
            }
        }

        if ($plan->max_vpn_peers !== 0) {
            $modules[] = 'vpn';
        }

        return array_values(array_unique($modules));
    }

    /**
     * @return array<string, int>
     */
    private function limitsFromPlan(SubscriptionPlan $plan): array
    {
        return array_filter([
            'max_mikrotik' => (int) $plan->max_mikrotik,
            'max_ppp_users' => (int) $plan->max_ppp_users,
            'max_vpn_peers' => (int) $plan->max_vpn_peers,
        ], fn (int $value): bool => $value !== 0);
    }
}
