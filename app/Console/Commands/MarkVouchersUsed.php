<?php

namespace App\Console\Commands;

use App\Services\VoucherUsageTracker;
use Illuminate\Console\Command;

class MarkVouchersUsed extends Command
{
    protected $signature = 'vouchers:mark-used';

    protected $description = 'Deteksi voucher yang sudah digunakan berdasarkan sesi aktif di radacct';

    public function __construct(private readonly VoucherUsageTracker $tracker)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->tracker->markUsedFromRadacct();

        $this->info("Marked {$count} voucher(s) as used.");

        return self::SUCCESS;
    }
}
