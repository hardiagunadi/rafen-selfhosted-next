<?php

namespace App\Services;

use App\Models\SystemLicense;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SystemLicenseService
{
    private ?bool $storageReady = null;

    public function __construct(
        private readonly LicenseFingerprintService $fingerprintService,
        private readonly LicenseSignatureService $signatureService,
    ) {}

    public function getCurrent(): SystemLicense
    {
        if (! $this->isSelfHostedEnabled()) {
            return $this->makeFallbackLicense('Fitur lisensi self-hosted tidak aktif di deployment ini.', 'disabled');
        }

        if (! $this->isStorageReady()) {
            return $this->makeFallbackLicense($this->storageUnavailableMessage());
        }

        $license = SystemLicense::query()->first();

        if (! $license && File::exists((string) config('license.path'))) {
            return $this->syncFromDisk();
        }

        if ($license) {
            return $this->refreshStatus($license);
        }

        return new SystemLicense([
            'status' => 'missing',
            'grace_days' => (int) config('license.default_grace_days', 21),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        $license = $this->getCurrent();
        $publicKey = (string) config('license.public_key');

        return [
            'license' => $license,
            'expected_fingerprint' => $this->fingerprintService->generate(),
            'license_path' => (string) config('license.path'),
            'has_public_key' => $publicKey !== '',
            'file_exists' => File::exists((string) config('license.path')),
            'is_self_hosted_enabled' => $this->isSelfHostedEnabled(),
            'is_enforced' => $this->isEnforced(),
            'is_valid' => $license->is_valid,
            'status_label' => $this->statusLabel($license->status),
        ];
    }

    public function isSelfHostedEnabled(): bool
    {
        return (bool) config('license.self_hosted_enabled', false);
    }

    public function isEnforced(): bool
    {
        return $this->isSelfHostedEnabled() && (bool) config('license.enforce');
    }

    public function allowsAccess(): bool
    {
        if (! $this->isEnforced()) {
            return true;
        }

        return $this->getCurrent()->is_valid;
    }

    public function storeUploadedLicense(UploadedFile $file): SystemLicense
    {
        $destination = (string) config('license.path');
        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, $file->get());

        if (! $this->isStorageReady()) {
            return $this->makeFallbackLicense($this->storageUnavailableMessage());
        }

        return $this->syncFromDisk(now()->toImmutable());
    }

    public function syncFromDisk(?CarbonImmutable $verifiedAt = null): SystemLicense
    {
        if (! $this->isStorageReady()) {
            return $this->makeFallbackLicense($this->storageUnavailableMessage());
        }

        $verifiedAt ??= now()->toImmutable();
        $license = SystemLicense::query()->firstOrNew();
        $license->grace_days = (int) ($license->grace_days ?: config('license.default_grace_days', 21));
        $license->uploaded_at ??= $verifiedAt;

        $path = (string) config('license.path');
        if (! File::exists($path)) {
            return $this->persistInvalidLicense($license, 'missing', 'File lisensi belum ditemukan di server.', $verifiedAt);
        }

        $decodedPayload = json_decode((string) File::get($path), true);
        if (! is_array($decodedPayload)) {
            return $this->persistInvalidLicense($license, 'invalid', 'Format file lisensi tidak valid.', $verifiedAt);
        }

        $validationError = $this->validatePayload($decodedPayload);
        if ($validationError !== null) {
            return $this->persistInvalidLicense($license, 'invalid', $validationError, $verifiedAt, $decodedPayload);
        }

        if (! $this->signatureService->verify($decodedPayload)) {
            return $this->persistInvalidLicense($license, 'invalid', 'Signature lisensi tidak valid.', $verifiedAt, $decodedPayload);
        }

        $expectedFingerprint = $this->fingerprintService->generate();
        if (($decodedPayload['fingerprint'] ?? null) !== $expectedFingerprint) {
            return $this->persistInvalidLicense($license, 'invalid', 'Fingerprint lisensi tidak cocok dengan server ini.', $verifiedAt, $decodedPayload);
        }

        $domainError = $this->validateDomainAccess($decodedPayload);
        if ($domainError !== null) {
            return $this->persistInvalidLicense($license, 'invalid', $domainError, $verifiedAt, $decodedPayload);
        }

        $license->fill([
            'status' => 'active',
            'license_id' => $decodedPayload['license_id'],
            'customer_name' => $decodedPayload['customer_name'],
            'instance_name' => $decodedPayload['instance_name'],
            'fingerprint' => $decodedPayload['fingerprint'],
            'issued_at' => $decodedPayload['issued_at'],
            'expires_at' => $decodedPayload['expires_at'],
            'support_until' => $decodedPayload['support_until'] ?? null,
            'grace_days' => (int) ($decodedPayload['grace_days'] ?? config('license.default_grace_days', 21)),
            'domains' => $decodedPayload['domains'] ?? [],
            'modules' => $decodedPayload['modules'] ?? [],
            'limits' => $decodedPayload['limits'] ?? [],
            'payload' => Arr::except($decodedPayload, ['signature']),
            'validation_error' => null,
            'uploaded_at' => $license->uploaded_at ?? $verifiedAt,
            'last_verified_at' => $verifiedAt,
        ]);

        return $this->refreshStatus($license, true);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'grace' => 'Grace Period',
            'restricted' => 'Restricted Mode',
            'invalid' => 'Tidak Valid',
            'missing' => 'Belum Ada Lisensi',
            'disabled' => 'Nonaktif',
            default => ucfirst($status),
        };
    }

    private function refreshStatus(SystemLicense $license, bool $shouldSave = false): SystemLicense
    {
        if ($license->status === 'invalid' || $license->status === 'missing' || ! $license->expires_at instanceof Carbon) {
            if ($shouldSave || $license->exists) {
                $license->save();
            }

            return $license->exists ? $license->refresh() : $license;
        }

        $graceEndsAt = $license->expires_at->copy()->addDays((int) $license->grace_days);

        if ($license->expires_at->isFuture() || $license->expires_at->isToday()) {
            $license->status = 'active';
            $license->restricted_mode_at = null;
        } elseif ($graceEndsAt->isFuture() || $graceEndsAt->isToday()) {
            $license->status = 'grace';
            $license->restricted_mode_at = null;
        } else {
            $license->status = 'restricted';
            $license->restricted_mode_at ??= now();
        }

        if ($shouldSave || $license->exists) {
            $license->save();
        }

        return $license->exists ? $license->refresh() : $license;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload): ?string
    {
        foreach (['license_id', 'customer_name', 'instance_name', 'issued_at', 'expires_at', 'fingerprint', 'modules', 'limits', 'signature'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $payload)) {
                return "Field lisensi `{$requiredKey}` wajib ada.";
            }
        }

        if (! is_array($payload['modules']) || ! is_array($payload['limits'])) {
            return 'Field modules dan limits harus berupa array.';
        }

        if ($this->parseDate($payload['issued_at']) === null || $this->parseDate($payload['expires_at']) === null) {
            return 'Tanggal lisensi tidak valid.';
        }

        if (isset($payload['support_until']) && $payload['support_until'] !== null && $this->parseDate($payload['support_until']) === null) {
            return 'Tanggal support_until tidak valid.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function persistInvalidLicense(
        SystemLicense $license,
        string $status,
        string $validationError,
        CarbonImmutable $verifiedAt,
        ?array $payload = null,
    ): SystemLicense {
        $license->fill([
            'status' => $status,
            'license_id' => $payload['license_id'] ?? null,
            'customer_name' => $payload['customer_name'] ?? null,
            'instance_name' => $payload['instance_name'] ?? null,
            'fingerprint' => $payload['fingerprint'] ?? null,
            'issued_at' => $payload['issued_at'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'support_until' => $payload['support_until'] ?? null,
            'grace_days' => (int) ($payload['grace_days'] ?? $license->grace_days ?? config('license.default_grace_days', 21)),
            'domains' => is_array($payload['domains'] ?? null) ? $payload['domains'] : [],
            'modules' => is_array($payload['modules'] ?? null) ? $payload['modules'] : [],
            'limits' => is_array($payload['limits'] ?? null) ? $payload['limits'] : [],
            'payload' => is_array($payload) ? Arr::except($payload, ['signature']) : null,
            'validation_error' => $validationError,
            'last_verified_at' => $verifiedAt,
            'restricted_mode_at' => $status === 'restricted' ? ($license->restricted_mode_at ?? $verifiedAt) : null,
        ]);

        $license->save();

        return $license->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateDomainAccess(array $payload): ?string
    {
        $accessMode = $payload['access_mode'] ?? 'fingerprint_only';
        $allowedHosts = is_array($payload['domains'] ?? null) ? $payload['domains'] : [];

        if ($accessMode === 'fingerprint_only' || $allowedHosts === []) {
            return null;
        }

        $currentHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($currentHost === '') {
            return null;
        }

        foreach ($allowedHosts as $host) {
            if (is_string($host) && strtolower(trim($host)) === strtolower($currentHost)) {
                return null;
            }
        }

        return "Domain '{$currentHost}' tidak termasuk dalam daftar domain yang diizinkan lisensi ini.";
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (RuntimeException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isStorageReady(): bool
    {
        if ($this->storageReady !== null) {
            return $this->storageReady;
        }

        try {
            $this->storageReady = Schema::hasTable((new SystemLicense)->getTable());
        } catch (\Throwable) {
            $this->storageReady = false;
        }

        return $this->storageReady;
    }

    private function makeFallbackLicense(?string $validationError = null, string $status = 'missing'): SystemLicense
    {
        return new SystemLicense([
            'status' => $status,
            'grace_days' => (int) config('license.default_grace_days', 21),
            'validation_error' => $validationError,
        ]);
    }

    private function storageUnavailableMessage(): string
    {
        return 'Tabel system_licenses belum tersedia. Jalankan php artisan migrate terlebih dahulu.';
    }
}
