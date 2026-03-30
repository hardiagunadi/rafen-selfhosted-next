<?php

namespace App\Console\Commands;

use App\Services\WaMultiSessionManager;
use Illuminate\Console\Command;

class EnsureWaGatewayRunning extends Command
{
    protected $signature = 'wa-gateway:ensure-running';

    protected $description = 'Ensure wa-multi-session PM2 service is running in background';

    public function __construct(private WaMultiSessionManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->manager->ensureRunning();

        if (! ($result['success'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Gagal memastikan wa-multi-session berjalan.'));

            return self::FAILURE;
        }

        $data = $result['data'] ?? [];
        $running = ($data['running'] ?? false) ? 'RUNNING' : 'STOPPED';
        $pid = $data['pm2_pid'] ?? '-';
        $this->info('wa-multi-session '.$running.' (PID: '.$pid.')');

        return self::SUCCESS;
    }
}
