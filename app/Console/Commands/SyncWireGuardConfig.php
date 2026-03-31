<?php

namespace App\Console\Commands;

use App\Models\WgPeer;
use App\Services\WgPeerSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncWireGuardConfig extends Command
{
    protected $signature = 'wireguard:sync';

    protected $description = 'Sinkronkan konfigurasi WireGuard self-hosted ke file wg0.conf.';

    public function handle(WgPeerSynchronizer $synchronizer): int
    {
        if (! Schema::hasTable('wg_peers')) {
            $this->warn('Tabel wg_peers belum tersedia, sinkronisasi WireGuard dilewati.');

            return self::SUCCESS;
        }

        $peers = WgPeer::query()->orderBy('name')->get();

        try {
            $synchronizer->syncAll($peers);
        } catch (Throwable $throwable) {
            $this->warn('Sinkronisasi WireGuard dilewati: '.$throwable->getMessage());

            return self::SUCCESS;
        }

        if ($peers->isNotEmpty()) {
            WgPeer::query()->where('is_active', true)->update(['last_synced_at' => now()]);
        }

        $this->info('Konfigurasi WireGuard berhasil disinkronkan.');
        $this->line('Peer aktif: '.$peers->where('is_active', true)->count());
        $this->line('Config path: '.(string) config('wg.config_path'));

        return self::SUCCESS;
    }
}
