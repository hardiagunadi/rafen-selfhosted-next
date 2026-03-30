<?php

namespace Database\Seeders\MixRadius;

use App\Models\MikrotikConnection;
use App\Models\ProfileGroup;
use Illuminate\Database\Seeder;

class ProfileGroupSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_profile_group');

        foreach ($rows as $row) {
            $mikrotik = MikrotikConnection::where('mixradius_shortname', $row['nasshortname'])->first();

            ProfileGroup::updateOrCreate(
                ['mixradius_id' => (string) $row['id']],
                [
                    'name'                   => $row['name'],
                    'owner'                  => null,
                    'mikrotik_connection_id' => $mikrotik?->id,
                    'type'                   => strtolower($row['type']) === 'ppp' ? 'pppoe' : 'hotspot',
                    'ip_pool_mode'           => $this->mapPoolMode($row['pool_module'] ?? null),
                    'ip_pool_name'           => $row['pool_name'] ?? null,
                    'ip_address'             => $row['local_address'] ?? null,
                    'netmask'                => null,
                    'range_start'            => $row['first_address'] ?? null,
                    'range_end'              => $row['last_address'] ?? null,
                    'dns_servers'            => $row['dns_servers'] ?? null,
                    'parent_queue'           => $row['parent_name'] ?? null,
                    'host_min'               => null,
                    'host_max'               => null,
                ]
            );
        }

        $this->command->info('Profile groups imported: '.count($rows));
    }

    private function mapPoolMode(?string $poolModule): string
    {
        if (empty($poolModule)) {
            return 'group_only';
        }

        if (in_array(strtolower($poolModule), ['radius', 'sql', 'mikrotik-ippool', 'sql-ippool'])) {
            return 'sql';
        }

        return 'group_only';
    }
}
