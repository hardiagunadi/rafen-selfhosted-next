<?php

namespace App\Services;

use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VoucherUsageTracker
{
    /**
     * Cek radacct untuk voucher unused yang sudah pernah login (aktif maupun sudah stop).
     * Set status=used, used_at dari acctstarttime, expired_at dari masa_aktif profil.
     * Returns jumlah voucher yang diupdate.
     */
    public function markUsedFromRadacct(): int
    {
        $unusedVouchers = Voucher::query()
            ->where('status', 'unused')
            ->whereNotNull('username')
            ->with('hotspotProfile')
            ->get();

        if ($unusedVouchers->isEmpty()) {
            return 0;
        }

        $usernames = $unusedVouchers->pluck('username')->filter()->unique()->values()->all();
        if (empty($usernames)) {
            return 0;
        }

        // Ambil waktu login pertama per username (aktif maupun sudah stop)
        $startTimes = DB::table('radacct')
            ->select('username', DB::raw('MIN(acctstarttime) as first_start'))
            ->whereIn('username', $usernames)
            ->whereNotNull('acctstarttime')
            ->groupBy('username')
            ->get()
            ->keyBy('username');

        $now = Carbon::now();
        $updated = 0;

        foreach ($unusedVouchers as $voucher) {
            if (! isset($startTimes[$voucher->username])) {
                continue;
            }

            $firstStart = $startTimes[$voucher->username]->first_start ?? null;
            $usedAt = $firstStart
                ? Carbon::parse($firstStart)
                : $now;

            $expiredAt = $voucher->hotspotProfile?->computeExpiredAt($usedAt);

            $voucher->update([
                'status' => 'used',
                'used_at' => $usedAt,
                'expired_at' => $expiredAt,
            ]);
            $updated++;
        }

        return $updated;
    }
}
