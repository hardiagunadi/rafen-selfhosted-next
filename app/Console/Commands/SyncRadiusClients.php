<?php

namespace App\Console\Commands;

use App\Services\RadiusClientsSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncRadiusClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radius:sync-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate clients.conf from DB and reload FreeRADIUS';

    public function __construct(private RadiusClientsSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->synchronizer->sync();
            $this->info('clients.conf generated and RADIUS reloaded.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
