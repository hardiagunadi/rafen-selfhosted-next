<?php

namespace App\Console\Commands;

use App\Services\SelfHostedHeartbeatService;
use App\Services\SelfHostedUpdateStatusService;
use Illuminate\Console\Command;

class CheckSelfHostedUpdate extends Command
{
    protected $signature = 'self-hosted:update:check';

    protected $description = 'Cek release manifest terbaru untuk instance self-hosted dan simpan snapshot status update.';

    public function handle(
        SelfHostedHeartbeatService $heartbeatService,
        SelfHostedUpdateStatusService $updateStatusService,
    ): int
    {
        $snapshot = $updateStatusService->check();

        $this->line('Channel          : '.($snapshot['channel'] ?? '-'));
        $this->line('Current Version  : '.($snapshot['current_version'] ?? '-'));
        $this->line('Current Commit   : '.($snapshot['current_commit'] ?? '-'));
        $this->line('Latest Version   : '.($snapshot['latest_version'] ?? '-'));
        $this->line('Latest Commit    : '.($snapshot['latest_commit'] ?? '-'));
        $this->line('Manifest URL     : '.($snapshot['latest_manifest_url'] ?? $snapshot['manifest_url'] ?? '-'));
        $this->line('Check Status     : '.($snapshot['last_check_status'] ?? '-'));
        $this->line('Message          : '.($snapshot['last_check_message'] ?? '-'));

        if (($snapshot['last_check_status'] ?? null) !== 'ok') {
            return self::FAILURE;
        }

        $this->info(($snapshot['update_available'] ?? false)
            ? 'Update tersedia.'
            : 'Instance sudah menggunakan release terbaru.');

        $heartbeat = $heartbeatService->submitBestEffort();

        if (($heartbeat['is_sent'] ?? false) === true) {
            $this->line('Heartbeat        : status instance berhasil dikirim ke SaaS.');
        }

        return self::SUCCESS;
    }
}
