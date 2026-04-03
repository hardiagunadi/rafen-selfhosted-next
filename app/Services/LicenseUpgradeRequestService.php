<?php

namespace App\Services;

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
            'fingerprint' => $this->fingerprintService->generate(),
            'current_license' => $currentInfo,
            'requested_upgrade' => $requestedUpgrade,
        ];
    }
}
