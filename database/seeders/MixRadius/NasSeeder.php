<?php

namespace Database\Seeders\MixRadius;

use App\Models\MikrotikConnection;
use App\Models\User;
use Illuminate\Database\Seeder;

class NasSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('nas');

        $defaultOwner = User::where('is_super_admin', true)->first()
            ?? User::first();

        foreach ($rows as $row) {
            MikrotikConnection::updateOrCreate(
                ['mixradius_shortname' => $row['shortname']],
                [
                    'owner_id'              => $defaultOwner->id,
                    'name'                  => $row['shortname'],
                    'host'                  => $row['nasname'],
                    'api_port'              => (int) ($row['api_port'] ?? 8728),
                    'api_ssl_port'          => 8729,
                    'use_ssl'               => false,
                    'username'              => $row['api_username'] ?? 'admin',
                    'password'              => $row['api_password'] ?? '',
                    'radius_secret'         => $row['secret'] ?? 'secret',
                    'ros_version'           => '7',
                    'api_timeout'           => 10,
                    'notes'                 => $row['description'] ?? $row['region'] ?? null,
                    'is_active'             => true,
                    'auth_port'             => 1812,
                    'acct_port'             => 1813,
                    'timezone'              => $this->normalizeTimezone($row['timezone'] ?? null),
                ]
            );
        }

        $this->command->info('NAS imported: '.count($rows));
    }

    private function normalizeTimezone(?string $tz): string
    {
        if (empty($tz)) {
            return 'Asia/Jakarta';
        }

        // MixRadius stores "+07:00" format, convert to named timezone
        $map = [
            '+07:00' => 'Asia/Jakarta',
            '+08:00' => 'Asia/Makassar',
            '+09:00' => 'Asia/Jayapura',
        ];

        return $map[$tz] ?? 'Asia/Jakarta';
    }
}
