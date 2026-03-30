<?php

namespace Database\Seeders\MixRadius;

use App\Models\BandwidthProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class BandwidthSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_bandwidth');

        foreach ($rows as $row) {
            BandwidthProfile::updateOrCreate(
                ['mixradius_id' => (string) $row['id']],
                [
                    'name'              => $row['name_bw'],
                    'upload_min_mbps'   => $this->toMbps($row['min_rate_up'], $row['min_rate_up_unit']),
                    'upload_max_mbps'   => $this->toMbps($row['max_rate_up'], $row['max_rate_up_unit']),
                    'download_min_mbps' => $this->toMbps($row['min_rate_down'], $row['min_rate_down_unit']),
                    'download_max_mbps' => $this->toMbps($row['max_rate_down'], $row['max_rate_down_unit']),
                    'owner'             => null,
                ]
            );
        }

        $this->command->info('Bandwidth profiles imported: '.count($rows));
    }

    private function toMbps(mixed $value, mixed $unit): float
    {
        $val = (float) $value;
        if (strtolower((string) $unit) === 'kbps') {
            return round($val / 1000, 3);
        }

        return $val;
    }
}
