<?php

namespace App\Console\Commands;

use App\Http\Controllers\ShiftController;
use App\Models\TenantSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShiftSendReminders extends Command
{
    protected $signature = 'shifts:send-reminders';

    protected $description = 'Kirim reminder shift besok ke semua pegawai via WA';

    public function handle(ShiftController $shiftController): int
    {
        $tenants = TenantSettings::where('shift_feature_enabled', true)->pluck('user_id');

        $totalSent = 0;

        foreach ($tenants as $ownerId) {
            try {
                $sent = $shiftController->dispatchRemindersForOwner((int) $ownerId);
                $totalSent += $sent;
                if ($sent > 0) {
                    $this->line("Tenant {$ownerId}: {$sent} reminder terkirim.");
                }
            } catch (\Throwable $e) {
                Log::warning("ShiftSendReminders: error for tenant {$ownerId}", ['error' => $e->getMessage()]);
                $this->warn("Tenant {$ownerId}: error — {$e->getMessage()}");
            }
        }

        $this->info("Total reminder terkirim: {$totalSent}");

        return self::SUCCESS;
    }
}
