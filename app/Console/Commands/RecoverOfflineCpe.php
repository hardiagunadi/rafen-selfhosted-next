<?php

namespace App\Console\Commands;

use App\Models\CpeDevice;
use App\Models\TenantSettings;
use App\Services\GenieAcsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecoverOfflineCpe extends Command
{
    protected $signature   = 'cpe:recover-offline';
    protected $description = 'Send Connection Request to offline CPE devices to force TR-069 re-inform';

    public function handle(): int
    {
        $minOfflineHours = (int) config('genieacs.recovery_min_offline_hours', 2);
        $retryHours      = (int) config('genieacs.recovery_retry_hours', 4);
        $batchSize       = (int) config('genieacs.recovery_batch_size', 20);

        Log::debug('cpe:recover-offline started', compact('minOfflineHours', 'retryHours', 'batchSize'));

        $cutoff = now()->subHours($minOfflineHours);

        // Ambil device offline yang sudah melewati threshold, urut terlama dulu
        $devices = CpeDevice::query()
            ->where('status', 'offline')
            ->whereNotNull('genieacs_device_id')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')
                  ->orWhere('last_seen_at', '<=', $cutoff);
            })
            ->orderBy('last_seen_at')   // terlama offline → prioritas lebih tinggi
            ->limit($batchSize)
            ->get(['id', 'genieacs_device_id', 'param_profile', 'owner_id']);

        if ($devices->isEmpty()) {
            $this->line('No offline devices eligible for recovery.');

            return self::SUCCESS;
        }

        $this->line("Found {$devices->count()} offline device(s) to attempt recovery.");

        // Build per-tenant GenieACS client map — sama dengan pola SyncCpeGenieacs
        $ownerIds  = $devices->pluck('owner_id')->unique()->filter();
        $clientMap = [];

        foreach ($ownerIds as $ownerId) {
            $settings            = TenantSettings::where('user_id', $ownerId)->first();
            $clientMap[$ownerId] = $settings
                ? GenieAcsClient::fromTenantSettings($settings)
                : new GenieAcsClient();
        }

        $attempted = 0;
        $woke      = 0;
        $skipped   = 0;

        foreach ($devices as $cpe) {
            $cacheKey = 'cpe_recovery_attempt_' . $cpe->id;

            if (Cache::has($cacheKey)) {
                $skipped++;
                continue;
            }

            $client  = $clientMap[$cpe->owner_id] ?? new GenieAcsClient();
            $profile = $cpe->param_profile ?? 'igd';

            try {
                $reached = $client->sendConnectionRequest($cpe->genieacs_device_id, $profile);

                // Catat percobaan agar tidak spam — baik berhasil maupun gagal (202)
                Cache::put($cacheKey, true, now()->addHours($retryHours));

                $attempted++;

                if ($reached) {
                    $woke++;
                    Log::info('cpe:recover-offline: Connection Request succeeded', [
                        'cpe_id'    => $cpe->id,
                        'device_id' => $cpe->genieacs_device_id,
                    ]);
                    $this->line("  [OK]  {$cpe->genieacs_device_id}");
                } else {
                    Log::info('cpe:recover-offline: device unreachable (202)', [
                        'cpe_id'    => $cpe->id,
                        'device_id' => $cpe->genieacs_device_id,
                    ]);
                    $this->line("  [202] {$cpe->genieacs_device_id} — unreachable, task deleted");
                }
            } catch (\Throwable $e) {
                // Satu device error tidak boleh menghentikan device lain
                Log::warning('cpe:recover-offline: exception', [
                    'cpe_id'    => $cpe->id,
                    'device_id' => $cpe->genieacs_device_id,
                    'error'     => $e->getMessage(),
                ]);
                $this->line("  [ERR] {$cpe->genieacs_device_id} — {$e->getMessage()}");

                // Cache 1 jam (bukan $retryHours) agar outage NBI sementara
                // tidak mengunci device hingga 4 jam
                Cache::put($cacheKey, true, now()->addHour());
            }
        }

        $unreachable = $attempted - $woke;
        $this->info("Done. Attempted: {$attempted}, Woke: {$woke}, Unreachable: {$unreachable}, Skipped: {$skipped}.");
        Log::info('cpe:recover-offline done', compact('attempted', 'woke', 'skipped'));

        return self::SUCCESS;
    }
}
