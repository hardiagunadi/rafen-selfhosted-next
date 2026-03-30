<?php

namespace App\Console\Commands;

use App\Models\CpeDevice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\GenieAcsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncCpeGenieacs extends Command
{
    protected $signature   = 'cpe:sync-genieacs';
    protected $description = 'Auto-link GenieACS devices to PPP users based on PPPoE username';

    public function handle(): int
    {
        Log::debug('cpe:sync-genieacs started');

        // Group tenants that have GenieACS configured (or use global config)
        $tenantIds = PppUser::query()
            ->whereDoesntHave('cpeDevice')
            ->distinct()
            ->pluck('owner_id');

        if ($tenantIds->isEmpty()) {
            return self::SUCCESS;
        }

        $linked         = 0;
        $skipped        = 0;
        $newlyLinkedIds = [];

        // Build per-tenant GenieACS client map
        $clientMap = [];
        foreach ($tenantIds as $ownerId) {
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            $clientMap[$ownerId] = $settings
                ? GenieAcsClient::fromTenantSettings($settings)
                : new GenieAcsClient();
        }

        // Fetch all GenieACS devices once per unique client (group by base URL)
        $devicesByUrl = [];
        foreach ($clientMap as $ownerId => $client) {
            $url = $client->getBaseUrl();
            if (! isset($devicesByUrl[$url])) {
                // Ensure default presets exist in this GenieACS instance so all
                // modems receive the correct CR credentials and inform interval.
                try {
                    $client->ensureDefaultPresets();
                } catch (\Throwable $e) {
                    Log::warning('cpe:sync-genieacs: ensureDefaultPresets failed', [
                        'url'   => $url,
                        'error' => $e->getMessage(),
                    ]);
                }

                $devicesByUrl[$url] = [
                    'client'  => $client,
                    'devices' => $client->listDevices(),
                ];
            }
        }

        // Build lookup: pppoe_username → genieacs device doc, per base URL
        $lookupByUrl = [];
        foreach ($devicesByUrl as $url => $data) {
            $lookup = [];
            foreach ($data['devices'] as $dev) {
                // Try to extract PPPoE username from device doc
                $username = $data['client']->getParamValue($dev, 'pppoe_username');
                if ($username) {
                    $lookup[strtolower($username)] = $dev;
                }
            }
            $lookupByUrl[$url] = $lookup;
        }

        // Process unlinked PPP users
        $unlinked = PppUser::query()->whereDoesntHave('cpeDevice')->get();

        foreach ($unlinked as $pppUser) {
            $client = $clientMap[$pppUser->owner_id] ?? new GenieAcsClient();
            $url    = $client->getBaseUrl();
            $lookup = $lookupByUrl[$url] ?? [];
            $key    = strtolower($pppUser->username);

            if (! isset($lookup[$key])) {
                $skipped++;
                continue;
            }

            $genieDevice = $lookup[$key];
            $profile     = $client->detectParamProfile($genieDevice);

            $cpe = new CpeDevice([
                'ppp_user_id' => $pppUser->id,
                'owner_id'    => $pppUser->owner_id,
            ]);
            $cpe->updateFromGenieacs($genieDevice);
            $cpe->save();

            // Trigger background refresh of full parameter tree.
            // Hapus pending tasks lama dulu agar tidak menumpuk.
            try {
                $rootObj = isset($genieDevice['InternetGatewayDevice'])
                    ? 'InternetGatewayDevice'
                    : 'Device';
                try {
                    $client->deleteDeviceTasks($genieDevice['_id']);
                } catch (\Throwable) {
                    // Non-fatal — lanjut refresh meski delete gagal
                }
                $client->refreshObject($genieDevice['_id'], $rootObj);
            } catch (\Throwable) {
                // Non-fatal — params will be fetched on next inform
            }

            $newlyLinkedIds[] = $genieDevice['_id'];
            Log::info("cpe:sync-genieacs linked {$pppUser->username} → {$genieDevice['_id']}");
            $this->line("Linked: {$pppUser->username} → {$genieDevice['_id']}");
            $linked++;
        }

        if ($linked > 0) {
            Log::info("cpe:sync-genieacs done. Linked: {$linked}");
        }

        // Update status + last_seen_at untuk linked CpeDevices dari _lastInform GenieACS
        $allLinkedCpes = CpeDevice::query()
            ->whereNotNull('ppp_user_id')
            ->get(['id', 'genieacs_device_id', 'status', 'last_seen_at']);

        $linkedMap = $allLinkedCpes->keyBy('genieacs_device_id');
        $threshold = (int) config('genieacs.online_threshold_minutes', 70);

        foreach ($devicesByUrl as $url => $data) {
            foreach ($data['devices'] as $dev) {
                $devId = $dev['_id'] ?? null;
                if (! $devId || ! $linkedMap->has($devId)) {
                    continue;
                }
                $cpe        = $linkedMap->get($devId);
                $lastInform = isset($dev['_lastInform'])
                    ? \Carbon\Carbon::parse($dev['_lastInform'])
                    : null;
                $newStatus = $lastInform && $lastInform->diffInMinutes(now()) < $threshold
                    ? 'online' : 'offline';

                if ($cpe->status !== $newStatus || ($lastInform && $cpe->last_seen_at?->ne($lastInform))) {
                    CpeDevice::where('id', $cpe->id)->update([
                        'status'       => $newStatus,
                        'last_seen_at' => $lastInform,
                    ]);
                }
            }
        }

        // Kirim refreshObject untuk device yang belum ada PPPoE username dan masih aktif (inform < 2 jam).
        // Skip device offline agar task tidak menumpuk di GenieACS tanpa batas.
        $cutoff = now()->subHours(2);

        foreach ($devicesByUrl as $url => $data) {
            foreach ($data['devices'] as $dev) {
                $id = $dev['_id'] ?? null;
                if (! $id || str_starts_with($id, 'DISCOVERYSERVICE-')) {
                    continue;
                }

                // Skip device yang tidak inform dalam 2 jam — kemungkinan offline
                $lastInform = isset($dev['_lastInform'])
                    ? \Carbon\Carbon::parse($dev['_lastInform'])
                    : null;
                if (! $lastInform || $lastInform->lt($cutoff)) {
                    continue;
                }

                $devUsername = $data['client']->getParamValue($dev, 'pppoe_username');
                $isLinked    = $linkedMap->has($id) || in_array($id, $newlyLinkedIds);

                if (! $devUsername) {
                    // Device belum punya PPPoE username — refresh WAN object saja
                    $profile = $data['client']->detectParamProfile($dev);
                    $wanPath = config("genieacs.params.{$profile}.wan_object");
                    if (! $wanPath) {
                        continue;
                    }

                    // Rate-limit: hanya queue refreshObject 1x per 30 menit per device
                    // agar task tidak menumpuk di GenieACS saat sync tiap 5 menit.
                    $cacheKey = 'genieacs_unlinked_refresh_' . md5($id);
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    try {
                        $data['client']->refreshObject($id, $wanPath);
                        Cache::put($cacheKey, true, now()->addMinutes(30));
                        Log::debug("cpe:sync-genieacs: refreshObject queued for {$id} (no username, {$wanPath})");
                    } catch (\Throwable $e) {
                        Log::warning("cpe:sync-genieacs: refreshObject failed for {$id}", ['error' => $e->getMessage()]);
                    }
                } elseif (! $isLinked) {
                    // Device punya username tapi belum ter-link ke PPP user — safety net:
                    // refresh full root object agar GenieACS punya data terbaru untuk run berikutnya.
                    $rootObj  = isset($dev['InternetGatewayDevice']) ? 'InternetGatewayDevice' : 'Device';
                    $cacheKey = 'genieacs_unlinked_refresh_' . md5($id);
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    try {
                        $data['client']->refreshObject($id, $rootObj);
                        Cache::put($cacheKey, true, now()->addMinutes(30));
                        Log::debug("cpe:sync-genieacs: refreshObject queued for {$id} (has username but not linked)");
                    } catch (\Throwable $e) {
                        Log::warning("cpe:sync-genieacs: refreshObject failed for {$id}", ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        $this->info("Done. Linked: {$linked}, No device found: {$skipped}");

        return self::SUCCESS;
    }
}
