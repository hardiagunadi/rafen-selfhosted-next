<?php

namespace App\Console\Commands;

use App\Models\MikrotikConnection;
use App\Services\MikrotikPingService;
use Illuminate\Console\Command;
use Throwable;

class PingMikrotikOnce extends Command
{
    protected $signature   = 'mikrotik:ping-once';
    protected $description = 'Ping semua router MikroTik aktif sekali dan update status (dijalankan oleh scheduler)';

    public function __construct(private MikrotikPingService $pingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $connections = MikrotikConnection::query()
            ->where('is_active', true)
            ->get();

        foreach ($connections as $connection) {
            try {
                $this->pingService->ping($connection);
            } catch (Throwable $e) {
                $this->error("Ping gagal untuk {$connection->name}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
