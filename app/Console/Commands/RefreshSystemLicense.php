<?php

namespace App\Console\Commands;

use App\Services\ServerHealthService;
use App\Services\SystemLicenseService;
use Illuminate\Console\Command;

class RefreshSystemLicense extends Command
{
    protected $signature = 'license:refresh';

    protected $description = 'Baca ulang dan verifikasi file lisensi dari disk.';

    public function handle(
        SystemLicenseService $systemLicenseService,
        ServerHealthService $serverHealthService
    ): int
    {
        $license = $systemLicenseService->syncFromDisk();
        $statusLabel = $systemLicenseService->statusLabel($license->status);

        $this->info('Lisensi berhasil direfresh.');
        $this->line('Status      : '.$statusLabel);
        $this->line('License ID  : '.($license->license_id ?: '-'));
        $this->line('Expires At  : '.($license->expires_at?->toDateString() ?: '-'));

        if ($license->validation_error) {
            $this->warn('Validation   : '.$license->validation_error);
        }

        if ($license->is_valid) {
            $summary = $serverHealthService->startInactiveLicensedServices();

            $this->line('Bootstrapped : '
                .count($summary['started']).' started, '
                .count($summary['already_running']).' already running, '
                .count($summary['failed']).' failed');

            foreach ($summary['failed'] as $failure) {
                $this->warn('Service      : '.$failure['name'].' => '.$failure['message']);
            }
        }

        return self::SUCCESS;
    }
}
