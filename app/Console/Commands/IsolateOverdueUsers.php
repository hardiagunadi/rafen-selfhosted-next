<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class IsolateOverdueUsers extends Command
{
    protected $signature = 'billing:isolate-overdue
                            {--dry-run : Tampilkan tanpa mengubah data}
                            {--owner-id= : Filter per tenant (opsional)}';

    protected $description = 'Isolir user PPP yang sudah melewati jatuh tempo dan belum bayar';

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $ownerId = $this->option('owner-id');
        $today   = Carbon::today()->toDateString();

        $query = PppUser::query()
            ->where('status_akun', 'enable')
            ->where('aksi_jatuh_tempo', 'isolir')
            ->where('status_bayar', 'belum_bayar')
            ->whereNotNull('jatuh_tempo')
            ->where('jatuh_tempo', '<=', $today)
            ->with(['profile', 'owner']);

        if ($ownerId) {
            $query->where('owner_id', (int) $ownerId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->line('Tidak ada user yang perlu diisolir.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$users->count()} user overdue (jatuh_tempo <= {$today}).");

        $isolated = 0;
        $skipped  = 0;

        $isolirSync  = app(IsolirSynchronizer::class);
        $radiusSync  = app(RadiusReplySynchronizer::class);

        // Cache settings per owner agar tidak query DB berulang
        $settingsCache = [];

        foreach ($users as $user) {
            $ownerKey = (int) $user->owner_id;

            if (! isset($settingsCache[$ownerKey])) {
                $settingsCache[$ownerKey] = TenantSettings::getOrCreate($ownerKey);
            }

            $settings = $settingsCache[$ownerKey];

            if (! $settings->auto_isolate_unpaid) {
                $skipped++;
                $this->line("  [skip] {$user->username} — auto_isolate_unpaid dinonaktifkan untuk tenant ini.");
                continue;
            }

            if ($dryRun) {
                $this->info("  [dry-run] Akan isolir: {$user->username} (jatuh_tempo: {$user->jatuh_tempo})");
                $isolated++;
                continue;
            }

            try {
                $user->update(['status_akun' => 'isolir']);
                $user->refresh();

                $radiusSync->syncSingleUser($user);
                $isolirSync->isolate($user);

                $isolated++;
                $this->info("  [OK] {$user->username} diisolir (jatuh_tempo: {$user->jatuh_tempo})");

                \App\Services\PushNotificationService::sendToCustomer(
                    $user,
                    'Layanan Dibatasi',
                    'Layanan internet Anda dibatasi karena tagihan belum dibayar.',
                    ['tag' => 'isolir-'.$user->id, 'icon' => '/branding/notify-isolir.png']
                );
                \App\Services\PushNotificationService::sendToOwnerStaff(
                    (int) $user->owner_id,
                    'Pelanggan Diisolir',
                    ($user->customer_name ?? $user->username).' diisolir karena jatuh tempo.',
                    ['url' => route('ppp-users.index'), 'tag' => 'isolir-staff-'.$user->id, 'icon' => '/branding/notify-isolir.png'],
                    ['administrator', 'noc', 'it_support']
                );
            } catch (\Throwable $e) {
                $skipped++;
                $this->error("  [ERR] {$user->username}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Selesai. Isolated: {$isolated}, Skipped/Error: {$skipped}.");

        return self::SUCCESS;
    }
}
