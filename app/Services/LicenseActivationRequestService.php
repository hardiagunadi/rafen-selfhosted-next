<?php

namespace App\Services;

class LicenseActivationRequestService
{
    public function __construct(
        private readonly LicenseFingerprintService $fingerprintService,
        private readonly SystemLicenseService $systemLicenseService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function makePayload(): array
    {
        $currentLicense = $this->systemLicenseService->getCurrent();

        return [
            'app_name' => (string) config('app.name'),
            'app_url' => (string) config('app.url'),
            'app_env' => (string) config('app.env'),
            'generated_at' => now()->toIso8601String(),
            'server_name' => php_uname('n'),
            'fingerprint' => $this->fingerprintService->generate(),
            'current_license_status' => $currentLicense->status,
            'current_license_id' => $currentLicense->license_id,
        ];
    }
}
