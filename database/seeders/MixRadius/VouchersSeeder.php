<?php

namespace Database\Seeders\MixRadius;

use App\Models\HotspotProfile;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class VouchersSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_vouchers');

        $defaultOwner = User::where('is_super_admin', true)->first()
            ?? User::first();

        $count = 0;
        $chunk = [];

        foreach ($rows as $row) {
            $profile = HotspotProfile::where('name', $row['plan_name'] ?? '')->first();

            $status = $this->mapStatus($row['status'] ?? null);
            $expiredAt = $this->parseValidDate($row['expired_on'] ?? null);

            // If past expiry and still marked unused, override to expired
            if ($status === 'unused' && $expiredAt && $expiredAt->isPast()) {
                $status = 'expired';
            }

            $chunk[] = [
                'owner_id'           => $defaultOwner->id,
                'hotspot_profile_id' => $profile?->id,
                'profile_group_id'   => null,
                'batch_name'         => $row['plan_name'] ?? null,
                'code'               => $row['code'],
                'status'             => $status,
                'username'           => ! empty($row['username']) ? $row['username'] : $row['code'],
                'password'           => ! empty($row['secret']) ? $row['secret'] : $row['code'],
                'used_at'            => null,
                'expired_at'         => $expiredAt?->toDateTimeString(),
                'used_by_mac'        => ! empty($row['mac_address']) ? $row['mac_address'] : null,
                'used_by_ip'         => null,
                'mixradius_id'       => (string) $row['id'],
                'created_at'         => $row['created_date'] ?? now(),
                'updated_at'         => now(),
            ];

            // Batch insert every 500 rows to avoid memory issues
            if (count($chunk) >= 500) {
                $this->upsertChunk($chunk);
                $count += count($chunk);
                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            $this->upsertChunk($chunk);
            $count += count($chunk);
        }

        $this->command->info("Vouchers imported: {$count}");
    }

    private function parseValidDate(?string $dateStr): ?Carbon
    {
        if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $dt = Carbon::parse($dateStr);
            if ($dt->year < 2000 || $dt->year >= 9000) {
                return null;
            }

            return $dt;
        } catch (\Exception) {
            return null;
        }
    }

    private function mapStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'FINISH', 'USED'  => 'used',
            'EXPIRED'         => 'expired',
            default           => 'unused',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     */
    private function upsertChunk(array $chunk): void
    {
        Voucher::upsert(
            $chunk,
            ['mixradius_id'],
            ['owner_id', 'hotspot_profile_id', 'batch_name', 'code', 'status', 'username', 'password', 'used_at', 'expired_at', 'used_by_mac', 'updated_at']
        );
    }
}
