<?php

namespace App\Console\Commands;

use App\Models\MikrotikConnection;
use App\Services\MikrotikPingService;
use Illuminate\Console\Command;
use Throwable;

class PingMikrotikLoop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:ping-loop {--interval=10 : Interval detik}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ping Mikrotik hosts repeatedly and update online status';

    public function __construct(private MikrotikPingService $pingService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $interval = $interval > 0 ? $interval : 10;

        $this->info("Starting ping loop every {$interval}s. Ctrl+C to stop.");

        while (true) {
            $connections = MikrotikConnection::query()
                ->where('is_active', true)
                ->get();

            foreach ($connections as $connection) {
                try {
                    $this->pingService->ping($connection);
                    $this->line("{$connection->name} ({$connection->host}) => ".($connection->is_online ? 'online' : 'offline'));
                } catch (Throwable $exception) {
                    $this->error("Ping failed for {$connection->name}: ".$exception->getMessage());
                }
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
