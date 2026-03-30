<?php

namespace App\Console\Commands;

use App\Mail\TenantAccountDeleted;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CleanupExpiredTrialTenants extends Command
{
    protected $signature   = 'tenants:cleanup-expired-trials {--dry-run : Tampilkan daftar tenant yang akan dihapus tanpa menghapus}';
    protected $description = 'Hapus otomatis tenant trial yang sudah expired lebih dari 7 hari dan belum pernah berlangganan berbayar';

    public function handle(): int
    {
        $cutoff = now()->subDays(7);

        $candidates = User::where('subscription_status', 'expired')
            ->where('subscription_method', 'monthly')
            ->where('trial_days_remaining', 0)
            ->where('is_super_admin', false)
            ->whereNull('parent_id')
            ->where(function ($q) use ($cutoff) {
                $q->where('subscription_expires_at', '<=', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('subscription_expires_at')
                         ->where('registered_at', '<=', now()->subDays(21));
                  });
            })
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->whereIn('status', ['active', 'expired', 'cancelled']);
            })
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada tenant expired trial yang perlu dihapus.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN — {$candidates->count()} tenant akan dihapus:");
            foreach ($candidates as $tenant) {
                $this->line("  - [{$tenant->id}] {$tenant->name} ({$tenant->email}) | expired: {$tenant->subscription_expires_at} | registered: {$tenant->registered_at}");
            }
            return 0;
        }

        $deleted = 0;
        foreach ($candidates as $tenant) {
            // Send deletion notification email BEFORE deleting (capture data first)
            $tenantName  = $tenant->name;
            $tenantEmail = $tenant->email;
            try {
                if ($tenantEmail) {
                    Mail::to($tenantEmail)->send(new TenantAccountDeleted($tenantName, $tenantEmail));
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal kirim email penghapusan tenant', ['id' => $tenant->id, 'error' => $e->getMessage()]);
            }

            try {
                $this->deleteTenantData($tenant);
                Log::info('Auto-deleted expired trial tenant', ['id' => $tenant->id, 'email' => $tenantEmail]);
                $this->line("Dihapus: [{$tenant->id}] {$tenantName} ({$tenantEmail})");
                $deleted++;
            } catch (\Throwable $e) {
                Log::error('Gagal hapus expired trial tenant', ['id' => $tenant->id, 'error' => $e->getMessage()]);
                $this->error("Gagal hapus tenant [{$tenant->id}] {$tenantName}: " . $e->getMessage());
            }
        }

        $this->info("Selesai: {$deleted} tenant expired trial berhasil dihapus.");
        return 0;
    }

    private function deleteTenantData(User $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $ownerId = $tenant->id;

            DB::table('cpe_devices')->where('owner_id', $ownerId)->delete();
            DB::table('ppp_users')->where('owner_id', $ownerId)->delete();
            DB::table('hotspot_users')->where('owner_id', $ownerId)->delete();
            DB::table('ppp_profiles')->where('owner_id', $ownerId)->delete();
            DB::table('hotspot_profiles')->where('owner_id', $ownerId)->delete();
            DB::table('vouchers')->where('owner_id', $ownerId)->delete();
            DB::table('invoices')->where('owner_id', $ownerId)->delete();
            DB::table('transactions')->where('owner_id', $ownerId)->delete();
            DB::table('payments')->where('user_id', $ownerId)->delete();
            DB::table('mikrotik_connections')->where('owner_id', $ownerId)->delete();
            DB::table('olt_connections')->where('owner_id', $ownerId)->delete();
            DB::table('olt_onu_optics')->where('owner_id', $ownerId)->delete();
            DB::table('odps')->where('owner_id', $ownerId)->delete();
            DB::table('bank_accounts')->where('user_id', $ownerId)->delete();
            DB::table('bandwidth_profiles')->where('owner_id', $ownerId)->delete();
            DB::table('profile_groups')->where('owner_id', $ownerId)->delete();
            DB::table('finance_expenses')->where('owner_id', $ownerId)->delete();
            DB::table('wa_multi_session_devices')->where('user_id', $ownerId)->delete();
            DB::table('wa_tickets')->where('owner_id', $ownerId)->delete();
            DB::table('wa_conversations')->where('owner_id', $ownerId)->delete();
            DB::table('wa_keyword_rules')->where('owner_id', $ownerId)->delete();
            DB::table('outages')->where('owner_id', $ownerId)->delete();
            DB::table('shift_definitions')->where('owner_id', $ownerId)->delete();
            DB::table('shift_schedules')->where('owner_id', $ownerId)->delete();
            DB::table('activity_logs')->where('owner_id', $ownerId)->delete();
            DB::table('login_logs')->where('user_id', $ownerId)->delete();
            DB::table('subscriptions')->where('user_id', $ownerId)->delete();
            DB::table('tenant_settings')->where('user_id', $ownerId)->delete();

            DB::table('users')->where('parent_id', $ownerId)->delete();
            $tenant->delete();
        });
    }
}
