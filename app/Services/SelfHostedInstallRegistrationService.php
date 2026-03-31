<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
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

        $responsePayload = $response->json();
        $registryTokenUpdated = $this->syncRegistryTokenFromResponse(is_array($responsePayload) ? $responsePayload : []);

        return [
            'payload' => $payload,
            'response' => $responsePayload,
            'registry_token_updated' => $registryTokenUpdated,
        ];
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    private function syncRegistryTokenFromResponse(array $responsePayload): bool
    {
        $newToken = trim((string) ($responsePayload['registry_token'] ?? ''));

        if ($newToken === '') {
            return false;
        }

        $currentToken = trim((string) config('services.self_hosted_registry.token', ''));

        if ($currentToken === $newToken) {
            return false;
        }

        $this->writeEnvironmentValue('SELF_HOSTED_REGISTRY_TOKEN', $newToken);
        config()->set('services.self_hosted_registry.token', $newToken);

        return true;
    }

    private function writeEnvironmentValue(string $key, string $value): void
    {
        $environmentFilePath = app()->environmentFilePath();

        if (! File::exists($environmentFilePath)) {
            File::put($environmentFilePath, '');
        }

        $environmentContents = (string) File::get($environmentFilePath);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $line = $key.'='.$value;

        if (preg_match($pattern, $environmentContents)) {
            $environmentContents = (string) preg_replace($pattern, $line, $environmentContents);
        } else {
            $environmentContents = rtrim($environmentContents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($environmentFilePath, $environmentContents);
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
