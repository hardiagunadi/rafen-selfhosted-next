<?php

namespace Database\Seeders\MixRadius;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_users');

        foreach ($rows as $row) {
            $email = ! empty($row['email']) ? $row['email'] : $row['username'].'@mixradius.local';

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'                    => ! empty($row['fullname']) ? $row['fullname'] : $row['username'],
                    'password'                => $row['password'],
                    'role'                    => 'administrator',
                    'is_super_admin'          => strtolower($row['user_type'] ?? '') === 'administrator' && $row['username'] === 'wifiku',
                    'phone'                   => $row['phonenumber'] ?? null,
                    'address'                 => $row['address'] ?? null,
                    'subscription_status'     => 'active',
                    'subscription_expires_at' => now()->addYear(),
                    'registered_at'           => $row['creationdate'] ?? now(),
                ]
            );
        }

        $this->command->info('Users imported: '.count($rows));
    }
}
