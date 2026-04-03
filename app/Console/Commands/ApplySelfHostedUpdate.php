<?php

namespace App\Console\Commands;

use App\Services\SelfHostedHeartbeatService;
use App\Services\SelfHostedUpdateRunnerService;
use Illuminate\Console\Command;

class ApplySelfHostedUpdate extends Command
{
    protected $signature = 'self-hosted:update:apply
        {target? : Tag/ref/commit yang ingin dipasang. Default memakai manifest terbaru}
        {--dry-run : Jalankan preflight tanpa mengubah file atau database}
        {--skip-backup : Lewati backup walau manifest memintanya}
        {--yes : Konfirmasi apply update aktual}';

    protected $description = 'Terapkan release self-hosted terbaru secara manual-assisted dengan preflight, backup, dan rollback ref.';

    public function handle(
        SelfHostedHeartbeatService $heartbeatService,
        SelfHostedUpdateRunnerService $runnerService,
    ): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('yes')) {
            $this->warn('Apply update aktual memerlukan konfirmasi eksplisit. Tambahkan --yes atau jalankan --dry-run lebih dulu.');

            return self::INVALID;
        }

        $result = $runnerService->apply(
            target: $this->argument('target'),
            dryRun: $dryRun,
            skipBackup: (bool) $this->option('skip-backup'),
        );

        $this->line('Status           : '.($result['status'] ?? '-'));
        $this->line('Run ID           : '.($result['run_id'] ?? '-'));
        $this->line('Target Version   : '.($result['target_version'] ?? '-'));
        $this->line('Target Ref       : '.($result['target_ref'] ?? '-'));
        $this->line('Rollback Ref     : '.($result['rollback_ref'] ?? '-'));
        $this->line('Backup Path      : '.($result['backup_path'] ?? '-'));
        $this->line('Message          : '.($result['message'] ?? '-'));

        if (! empty($result['output_excerpt'])) {
            $this->newLine();
            $this->line($result['output_excerpt']);
        }

        $heartbeat = $heartbeatService->submitBestEffort();

        if (($heartbeat['is_sent'] ?? false) === true) {
            $this->newLine();
            $this->line('Heartbeat        : status instance berhasil dikirim ke SaaS.');
        }

        return match ($result['status'] ?? 'failed') {
            'success', 'dry_run' => self::SUCCESS,
            default => self::FAILURE,
        };
    }
}
