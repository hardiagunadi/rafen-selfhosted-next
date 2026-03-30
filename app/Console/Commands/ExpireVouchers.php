<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireVouchers extends Command
{
    protected $signature = 'vouchers:expire';

    protected $description = 'Delete expired vouchers (unused past expiry, or used past expiry) and remove from RADIUS';

    public function handle(): int
    {
        // Kumpulkan semua voucher yang sudah expired:
        // 1. unused dengan expired_at sudah lewat
        // 2. used dengan expired_at sudah lewat
        $vouchers = Voucher::query()
            ->whereIn('status', ['unused', 'used'])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->get(['id', 'username']);

        if ($vouchers->isEmpty()) {
            $this->info('No expired vouchers found.');
            return self::SUCCESS;
        }

        $usernames = $vouchers->pluck('username')->filter()->values()->all();
        $ids       = $vouchers->pluck('id')->all();

        // Hapus dari RADIUS
        if (! empty($usernames)) {
            DB::table('radcheck')->whereIn('username', $usernames)->delete();
            DB::table('radreply')->whereIn('username', $usernames)->delete();
        }

        // Hapus dari DB
        Voucher::whereIn('id', $ids)->delete();

        $this->info("Deleted {$vouchers->count()} expired voucher(s) and removed from RADIUS.");

        return self::SUCCESS;
    }
}
