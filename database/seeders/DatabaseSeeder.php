<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'role' => 'administrator',
                'is_super_admin' => true,
                'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
            ],
        );

        // Run subscription seeder
        $this->call(SubscriptionSeeder::class);
    }
}
