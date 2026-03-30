<?php

namespace App\Console\Commands;

use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use App\Services\ActiveSessionFetcher;
use App\Services\MikrotikApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncActiveSessions extends Command
{
    protected $signature   = 'sessions:sync';
    protected $description = 'Sync active PPPoE & Hotspot sessions from all online MikroTik routers';

    public function handle(): int
    {
        $routers = MikrotikConnection::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            $this->line('No online routers found.');
            return self::SUCCESS;
        }

        $pppTotal     = 0;
        $hotspotTotal = 0;
        $errors       = [];

        foreach ($routers as $router) {
            $fetcher = new ActiveSessionFetcher(new MikrotikApiClient($router));

            // Jika router diketahui offline dari ping terakhir, langsung mark inactive
            // tanpa buang waktu mencoba koneksi API ke MikroTik yang mati.
            if (! $router->is_online) {
                $fetcher->markAllInactive($router, 'pppoe');
                $fetcher->markAllInactive($router, 'hotspot');
                continue;
            }

            try {
                $pppTotal += $fetcher->syncPpp($router);
            } catch (RuntimeException $e) {
                $errors[] = "[{$router->name}] PPPoE: " . $e->getMessage();
                $fetcher->markAllInactive($router, 'pppoe');
            }

            try {
                $hotspotTotal += $fetcher->syncHotspot($router);
            } catch (RuntimeException $e) {
                $errors[] = "[{$router->name}] Hotspot: " . $e->getMessage();
                $fetcher->markAllInactive($router, 'hotspot');
            }
        }

        foreach ($errors as $err) {
            $this->warn($err);
        }

        // Mark orphan radius_accounts (no mikrotik_connection_id) as inactive — these are
        // legacy records from before multi-NAS support and should never appear as active.
        RadiusAccount::whereNull('mikrotik_connection_id')
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        // Close zombie sessions from NAS IPs not registered in MikrotikConnection.
        // These routers are unreachable or removed, so all their open sessions are stale.
        $knownNasIps = MikrotikConnection::pluck('host')->filter()->all();
        $now = now()->toDateTimeString();
        $unregisteredZombies = DB::table('radacct')
            ->whereNull('acctstoptime')
            ->whereNotIn('nasipaddress', $knownNasIps)
            ->update([
                'acctstoptime'       => $now,
                'acctupdatetime'     => $now,
                'acctterminatecause' => 'NAS-Request',
            ]);

        $this->info("Sync selesai — PPPoE: {$pppTotal}, Hotspot: {$hotspotTotal} sesi aktif. Zombie dari NAS tidak terdaftar: {$unregisteredZombies}.");

        return self::SUCCESS;
    }
}
