<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class LicenseUpgradeRequestService
{
    public function __construct(
        private readonly SystemLicenseService $licenseService,
        private readonly LicenseFingerprintService $fingerprintService,
    ) {}

    /**
     * Buat payload untuk file upgrade request yang akan dikirim ke vendor.
     *
     * @param  list<string>  $requestedModules
     * @param  array<string, int>  $requestedLimits
     * @return array<string, mixed>
     */
    public function makePayload(array $requestedModules, array $requestedLimits, ?string $notes): array
    {
        $currentLicense = $this->licenseService->getCurrent();

        $currentInfo = [
            'license_id' => $currentLicense->license_id,
            'status' => $currentLicense->status,
            'expires_at' => $currentLicense->expires_at?->toDateString(),
            'modules' => $currentLicense->modules ?? [],
            'limits' => $currentLicense->limits ?? [],
        ];

        $requestedUpgrade = [
            'modules' => array_values(array_filter($requestedModules, fn (mixed $m): bool => is_string($m) && $m !== '')),
            'limits' => $requestedLimits,
        ];

        if ($notes !== null && $notes !== '') {
            $requestedUpgrade['notes'] = $notes;
        }

        return [
            'type' => 'upgrade_request',
            'app_name' => (string) config('app.name'),
            'app_url' => (string) config('app.url'),
            'app_env' => (string) config('app.env'),
            'generated_at' => now()->toIso8601String(),
            'server_name' => php_uname('n'),
            'access_mode' => $this->detectAccessMode((string) config('app.url')),
            'fingerprint' => $this->fingerprintService->generate(),
            'current_license' => $currentInfo,
            'requested_upgrade' => $requestedUpgrade,
        ];
    }

    /**
     * @param  list<string>  $requestedModules
     * @param  array<string, int>  $requestedLimits
     * @return array{payload: array<string, mixed>, response: mixed, url: string}
     */
    public function submit(array $requestedModules, array $requestedLimits, ?string $notes): array
    {
        $url = $this->resolveUpgradeRequestUrl();
        $token = trim((string) config('services.self_hosted_registry.token', ''));

        if ($url === '' || $token === '') {
            throw new RuntimeException('SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi. Jalur online ke SaaS belum siap.');
        }

        $payload = $this->makePayload($requestedModules, $requestedLimits, $notes);

        try {
            $response = Http::timeout(20)
                ->withToken($token)
                ->acceptJson()
                ->post($url, $payload);

            if ($response->failed()) {
                $message = $response->json('message') ?? $response->body();

                throw new RuntimeException('HTTP '.$response->status().' '.$message);
            }
        } catch (RuntimeException $exception) {
            throw new RuntimeException('Request upgrade lisensi gagal dikirim: '.$exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('Request upgrade lisensi gagal dikirim: '.$exception->getMessage(), 0, $exception);
        }

        return [
            'payload' => $payload,
            'response' => $response->json(),
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusSnapshot(): array
    {
        $statusUrl = $this->resolveUpgradeRequestStatusUrl();
        $token = trim((string) config('services.self_hosted_registry.token', ''));
        $fingerprint = $this->fingerprintService->generate();

        if ($statusUrl === '' || $token === '') {
            return [
                'is_available' => false,
                'is_configured' => false,
                'message' => 'Status online belum tersedia karena SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.',
                'latest_request' => null,
                'recent_requests' => [],
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->get($statusUrl, [
                    'fingerprint' => $fingerprint,
                ]);

            if ($response->failed()) {
                $message = $response->json('message') ?? $response->body();

                throw new RuntimeException('HTTP '.$response->status().' '.$message);
            }
        } catch (RuntimeException $exception) {
            return [
                'is_available' => false,
                'is_configured' => true,
                'message' => 'Status request upgrade belum bisa diambil dari SaaS: '.$exception->getMessage(),
                'latest_request' => null,
                'recent_requests' => [],
            ];
        } catch (Throwable $exception) {
            return [
                'is_available' => false,
                'is_configured' => true,
                'message' => 'Status request upgrade belum bisa diambil dari SaaS: '.$exception->getMessage(),
                'latest_request' => null,
                'recent_requests' => [],
            ];
        }

        $payload = $response->json();

        return [
            'is_available' => true,
            'is_configured' => true,
            'message' => null,
            'latest_request' => $this->normalizeStatusRequest(is_array($payload['latest_request'] ?? null) ? $payload['latest_request'] : null),
            'recent_requests' => collect($payload['recent_requests'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => $this->normalizeStatusRequest($item))
                ->values()
                ->all(),
        ];
    }

    private function resolveUpgradeRequestUrl(): string
    {
        $registryUrl = trim((string) config('services.self_hosted_registry.url', ''));

        if ($registryUrl === '') {
            return '';
        }

        $rewrittenUrl = preg_replace(
            '#/install-registrations/?$#',
            '/license-upgrade-requests',
            $registryUrl,
            1,
            $replacementCount
        );

        if ($replacementCount === 1 && is_string($rewrittenUrl)) {
            return $rewrittenUrl;
        }

        return '';
    }

    private function resolveUpgradeRequestStatusUrl(): string
    {
        $upgradeRequestUrl = $this->resolveUpgradeRequestUrl();

        if ($upgradeRequestUrl === '') {
            return '';
        }

        $rewrittenUrl = preg_replace(
            '#/license-upgrade-requests/?$#',
            '/license-upgrade-requests/status',
            $upgradeRequestUrl,
            1,
            $replacementCount
        );

        if ($replacementCount === 1 && is_string($rewrittenUrl)) {
            return $rewrittenUrl;
        }

        return '';
    }

    private function detectAccessMode(string $appUrl): string
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'unknown';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return 'ip-based';
        }

        return 'domain-based';
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @return array<string, mixed>|null
     */
    private function normalizeStatusRequest(?array $request): ?array
    {
        if ($request === null) {
            return null;
        }

        return [
            ...$request,
            'requested_at_human' => $this->formatDateTime($request['requested_at'] ?? null),
            'fulfilled_at_human' => $this->formatDateTime($request['fulfilled_at'] ?? null),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->format('d M Y H:i');
    }
}
