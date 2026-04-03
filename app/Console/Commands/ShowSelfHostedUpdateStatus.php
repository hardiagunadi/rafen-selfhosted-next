<?php

namespace App\Console\Commands;

use App\Services\SelfHostedUpdateStatusService;
use Illuminate\Console\Command;

class ShowSelfHostedUpdateStatus extends Command
{
    protected $signature = 'self-hosted:update:status {--json : Tampilkan snapshot sebagai JSON}';

    protected $description = 'Tampilkan snapshot status update aplikasi self-hosted.';

    public function handle(SelfHostedUpdateStatusService $updateStatusService): int
    {
        $snapshot = $updateStatusService->snapshot();

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Channel          : '.($snapshot['channel'] ?? '-'));
        $this->line('Manifest URL     : '.($snapshot['manifest_url'] ?? '-'));
        $this->line('Current Version  : '.($snapshot['current_version'] ?? '-'));
        $this->line('Current Commit   : '.($snapshot['current_commit'] ?? '-'));
        $this->line('Current Ref      : '.($snapshot['current_ref'] ?? '-'));
        $this->line('Latest Version   : '.($snapshot['latest_version'] ?? '-'));
        $this->line('Latest Commit    : '.($snapshot['latest_commit'] ?? '-'));
        $this->line('Latest Ref       : '.($snapshot['latest_ref'] ?? '-'));
        $this->line('Update Available : '.(($snapshot['update_available'] ?? false) ? 'yes' : 'no'));
        $this->line('Last Checked At  : '.(($snapshot['last_checked_at'] ?? null)?->toIso8601String() ?? '-'));
        $this->line('Check Status     : '.($snapshot['last_check_status'] ?? '-'));
        $this->line('Message          : '.($snapshot['last_check_message'] ?? '-'));
        $this->line('Last Apply At    : '.(($snapshot['last_applied_at'] ?? null)?->toIso8601String() ?? '-'));
        $this->line('Apply Status     : '.($snapshot['last_apply_status'] ?? '-'));
        $this->line('Apply Message    : '.($snapshot['last_apply_message'] ?? '-'));
        $this->line('Rollback Ref     : '.($snapshot['rollback_ref'] ?? '-'));

        return self::SUCCESS;
    }
}
