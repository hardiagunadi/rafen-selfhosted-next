<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SelfHostedInstallRegistrationService
{
    public function __construct(
        private readonly LicenseActivationRequestService $licenseActivationRequestService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function makePayload(?string $adminName = null, ?string $adminEmail = null, ?string $adminPhone = null): array
    {
        $payload = $this->licenseActivationRequestService->makePayload();
        $payload['admin_name'] = $this->normalized($adminName);
        $payload['admin_email'] = $this->normalized($adminEmail);
        $payload['admin_phone'] = $this->normalized($adminPhone);
        $payload['access_mode'] = $this->detectAccessMode((string) ($payload['app_url'] ?? ''));

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function register(?string $adminName = null, ?string $adminEmail = null, ?string $adminPhone = null): array
    {
        $url = trim((string) config('services.self_hosted_registry.url', ''));
        $token = trim((string) config('services.self_hosted_registry.token', ''));

        if ($url === '' || $token === '') {
            throw new RuntimeException('SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.');
        }

        $payload = $this->makePayload($adminName, $adminEmail, $adminPhone);
        $response = Http::timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Registrasi install-time gagal: HTTP '.$response->status().' '.$response->body());
        }

        return [
            'payload' => $payload,
            'response' => $response->json(),
        ];
    }

    private function normalized(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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
