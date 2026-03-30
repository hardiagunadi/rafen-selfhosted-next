<?php

namespace App\Console\Commands;

use App\Services\HotspotRadiusSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncRadiusReplies extends Command
{
    protected $signature = 'radius:sync-replies';

    protected $description = 'Sync radcheck + radreply from ppp_users, hotspot_users, and vouchers';

    public function __construct(
        private RadiusReplySynchronizer  $synchronizer,
        private HotspotRadiusSynchronizer $hotspotSynchronizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $pppCount     = $this->synchronizer->sync();
            $hotspotCount = $this->hotspotSynchronizer->sync();

            $this->info("Synced {$pppCount} PPP users + {$hotspotCount} hotspot/voucher entries to radcheck/radreply.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
