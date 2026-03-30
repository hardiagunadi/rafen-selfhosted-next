<?php

namespace App\Services;

use App\Models\HotspotProfile;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;

class VoucherGeneratorService
{
    private const CODE_LENGTH = 8;

    private const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Generate a batch of unique voucher codes.
     *
     * @return Collection<int, Voucher>
     */
    public function generateBatch(HotspotProfile $profile, int $count, string $batchName, User $owner): Collection
    {
        $vouchers = collect();
        $attempts = 0;
        $maxAttempts = $count * 5;

        while ($vouchers->count() < $count && $attempts < $maxAttempts) {
            $code = $this->generateCode();
            $attempts++;

            if (Voucher::where('code', $code)->exists()) {
                continue;
            }

            $voucher = Voucher::create([
                'owner_id'           => $owner->effectiveOwnerId(),
                'hotspot_profile_id' => $profile->id,
                'profile_group_id'   => $profile->profile_group_id,
                'batch_name'         => $batchName,
                'code'               => $code,
                'status'             => 'unused',
                'username'           => $code,
                'password'           => $code,
            ]);

            $vouchers->push($voucher);
        }

        return $vouchers;
    }

    private function generateCode(): string
    {
        $charset = self::CHARSET;
        $charsetLen = strlen($charset);
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $charset[random_int(0, $charsetLen - 1)];
        }

        return $code;
    }
}
