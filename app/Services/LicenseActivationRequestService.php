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
            'access_mode' => $this->detectAccessMode((string) config('app.url')),
            'fingerprint' => $this->fingerprintService->generate(),
            'current_license_status' => $currentLicense->status,
            'current_license_id' => $currentLicense->license_id,
        ];
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
}
