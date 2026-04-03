<?php

namespace App\Console\Commands;

use App\Services\SelfHostedHeartbeatService;
use Illuminate\Console\Command;
use RuntimeException;

class SendSelfHostedHeartbeat extends Command
{
    protected $signature = 'self-hosted:heartbeat
        {--check-update : Refresh snapshot update lebih dulu sebelum heartbeat dikirim}';

    protected $description = 'Kirim heartbeat status instance self-hosted ke SaaS control plane.';

    public function handle(SelfHostedHeartbeatService $heartbeatService): int
    {
        if ((bool) config('license.self_hosted_enabled', false) === false) {
            $this->warn('Command ini hanya relevan untuk instance self-hosted.');

            return self::SUCCESS;
        }

        $url = $heartbeatService->resolveHeartbeatUrl();
        $token = trim((string) config('services.self_hosted_registry.token', ''));

        if ($url === '' || $token === '') {
            $this->line('Heartbeat dilewati karena SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.');

            return self::SUCCESS;
        }

        try {
            $result = $heartbeatService->submit((bool) $this->option('check-update'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
        $response = is_array($result['response'] ?? null) ? $result['response'] : [];

        $this->info('Heartbeat self-hosted berhasil dikirim.');
        $this->line('Endpoint       : '.($result['url'] ?? '-'));
        $this->line('Fingerprint    : '.((string) ($payload['fingerprint'] ?? '-')));
        $this->line('Current Version: '.((string) ($payload['current_version'] ?? '-')));
        $this->line('Latest Version : '.((string) ($payload['latest_version'] ?? '-')));
        $this->line('Update Status  : '.((string) ($payload['last_apply_status'] ?? '-')));
        $this->line('SaaS Status ID : '.((string) ($response['status_id'] ?? '-')));

        return self::SUCCESS;
    }
}
