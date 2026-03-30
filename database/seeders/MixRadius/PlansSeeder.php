<?php

namespace Database\Seeders\MixRadius;

use App\Models\BandwidthProfile;
use App\Models\HotspotProfile;
use App\Models\ProfileGroup;
use App\Models\PppProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_plans');

        $defaultOwner = User::where('is_super_admin', true)->first()
            ?? User::first();

        $pppCount = 0;
        $hotspotCount = 0;

        foreach ($rows as $row) {
            $bw = BandwidthProfile::where('name', $row['bw_name'])->first();
            $pg = ProfileGroup::where('name', $row['profile_group'])->first();
            $satuan = $this->convertValidityUnit($row['validity_unit']);
            $ppn = $this->extractTaxPercent($row['tax'] ?? '0%');

            if (strtoupper($row['type']) === 'PPP') {
                PppProfile::updateOrCreate(
                    ['mixradius_id' => (string) $row['id']],
                    [
                        'name'                 => $row['name_plan'],
                        'owner_id'             => $defaultOwner->id,
                        'harga_modal'          => (float) ($row['price'] ?? 0),
                        'harga_promo'          => (float) ($row['sell_price'] ?? $row['price'] ?? 0),
                        'ppn'                  => $ppn,
                        'profile_group_id'     => $pg?->id,
                        'bandwidth_profile_id' => $bw?->id,
                        'masa_aktif'           => max(0, (int) ($row['validity'] ?? 1)),
                        'satuan'               => $satuan,
                    ]
                );
                $pppCount++;
            } else {
                [$profileType, $limitType, $timeLimitValue, $timeLimitUnit, $quotaLimitValue, $quotaLimitUnit, $masaAktifValue, $masaAktifUnit] =
                    $this->mapHotspotLimits($row);

                HotspotProfile::updateOrCreate(
                    ['mixradius_id' => (string) $row['id']],
                    [
                        'name'                 => $row['name_plan'],
                        'owner_id'             => $defaultOwner->id,
                        'harga_jual'           => (float) ($row['sell_price'] ?? $row['price'] ?? 0),
                        'harga_promo'          => (float) ($row['promo_price'] ?? 0),
                        'ppn'                  => $ppn,
                        'bandwidth_profile_id' => $bw?->id,
                        'profile_group_id'     => $pg?->id,
                        'profile_type'         => $profileType,
                        'limit_type'           => $limitType,
                        'time_limit_value'     => $timeLimitValue,
                        'time_limit_unit'      => $timeLimitUnit,
                        'quota_limit_value'    => $quotaLimitValue,
                        'quota_limit_unit'     => $quotaLimitUnit,
                        'masa_aktif_value'     => $masaAktifValue,
                        'masa_aktif_unit'      => $masaAktifUnit,
                        'shared_users'         => max(1, (int) ($row['shared_users'] ?? 1)),
                        'prioritas'            => $this->mapPriority($row['priority'] ?? null),
                    ]
                );
                $hotspotCount++;
            }
        }

        $this->command->info("Plans imported: {$pppCount} PPP, {$hotspotCount} Hotspot");
    }

    private function convertValidityUnit(mixed $unit): string
    {
        return match (strtoupper((string) $unit)) {
            'H'   => 'jam',
            'D'   => 'hari',
            'Min' => 'menit',
            default => 'bulan',
        };
    }

    private function extractTaxPercent(mixed $tax): float
    {
        return (float) str_replace('%', '', (string) $tax);
    }

    /**
     * @return array{string, string|null, int|null, string|null, float|null, string|null, int|null, string|null}
     */
    private function mapHotspotLimits(array $row): array
    {
        $limitType = null;
        $timeLimitValue = null;
        $timeLimitUnit = null;
        $quotaLimitValue = null;
        $quotaLimitUnit = null;
        $masaAktifValue = null;
        $masaAktifUnit = null;

        $mixLimitType = strtoupper($row['limit_type'] ?? '');
        $profileType = (strtolower($row['typebp'] ?? 'unlimited') === 'limited') ? 'limited' : 'unlimited';

        if ($mixLimitType === 'TIME_LIMIT') {
            $profileType = 'limited';
            $limitType = 'time';
            $timeLimitValue = (int) ($row['time_limit'] ?? 1);
            $timeLimitUnit = $this->convertValidityUnit($row['time_unit'] ?? 'H');
        } elseif ($mixLimitType === 'DATA_LIMIT') {
            $profileType = 'limited';
            $limitType = 'quota';
            $quotaLimitValue = (float) ($row['data_limit'] ?? 0);
            $quotaLimitUnit = strtolower($row['data_unit'] ?? 'M') === 'g' ? 'gb' : 'mb';
        } else {
            $masaAktifValue = max(0, (int) ($row['validity'] ?? 1));
            $masaAktifUnit = $this->convertValidityUnit($row['validity_unit'] ?? 'M');
        }

        return [$profileType, $limitType, $timeLimitValue, $timeLimitUnit, $quotaLimitValue, $quotaLimitUnit, $masaAktifValue, $masaAktifUnit];
    }

    private function mapPriority(mixed $priority): string
    {
        $p = (int) $priority;
        if ($p < 1 || $p > 8) {
            return 'default';
        }

        return 'prioritas'.$p;
    }
}
