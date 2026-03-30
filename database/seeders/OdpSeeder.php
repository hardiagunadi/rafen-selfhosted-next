<?php

namespace Database\Seeders;

use App\Models\Odp;
use App\Models\User;
use Illuminate\Database\Seeder;

class OdpSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = User::query()
            ->where('is_super_admin', false)
            ->whereNull('parent_id')
            ->limit(5)
            ->get();

        foreach ($tenants as $tenant) {
            Odp::factory()->count(3)->create([
                'owner_id' => $tenant->id,
                'status' => 'active',
            ]);
        }
    }
}
