<?php

namespace App\Console\Commands;

use App\Services\SelfHostedInstallRegistrationService;
use Illuminate\Console\Command;
use RuntimeException;

class RegisterSelfHostedInstall extends Command
{
    protected $signature = 'self-hosted:register-install
        {--admin-name= : Nama admin awal instance self-hosted}
        {--admin-email= : Email admin awal instance self-hosted}
        {--admin-phone= : Nomor WhatsApp admin awal instance self-hosted}';

    protected $description = 'Kirim registrasi install-time self-hosted ke server SaaS pusat.';

    public function handle(SelfHostedInstallRegistrationService $selfHostedInstallRegistrationService): int
    {
        if ((bool) config('license.self_hosted_enabled', false) === false) {
            $this->warn('Command ini hanya relevan untuk instance self-hosted.');

            return self::SUCCESS;
        }

        $url = trim((string) config('services.self_hosted_registry.url', ''));
        $token = trim((string) config('services.self_hosted_registry.token', ''));

        if ($url === '' || $token === '') {
            $this->line('Registrasi install-time dilewati karena SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.');

            return self::SUCCESS;
        }

        try {
            $result = $selfHostedInstallRegistrationService->register(
                adminName: $this->optionString('admin-name'),
                adminEmail: $this->optionString('admin-email'),
                adminPhone: $this->optionString('admin-phone'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];
        $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];

        $this->info('Registrasi install-time self-hosted berhasil dikirim.');
        $this->line('Tenant ID     : '.((string) ($response['tenant_id'] ?? '-')));
        $this->line('Tenant Name   : '.((string) ($response['tenant_name'] ?? '-')));
        $this->line('Fingerprint   : '.((string) ($payload['fingerprint'] ?? '-')));
        if ((bool) ($result['registry_token_updated'] ?? false)) {
            $this->line('Registry Token: diperbarui otomatis dari SaaS untuk instance ini.');
        }

        return self::SUCCESS;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
