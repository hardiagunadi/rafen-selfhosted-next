<?php

namespace Database\Seeders;

use Database\Seeders\MixRadius\BandwidthSeeder;
use Database\Seeders\MixRadius\HotspotCustomersSeeder;
use Database\Seeders\MixRadius\MixRadiusSqlParser;
use Database\Seeders\MixRadius\NasSeeder;
use Database\Seeders\MixRadius\PlansSeeder;
use Database\Seeders\MixRadius\PppCustomersSeeder;
use Database\Seeders\MixRadius\ProfileGroupSeeder;
use Database\Seeders\MixRadius\TransactionsSeeder;
use Database\Seeders\MixRadius\UsersSeeder;
use Database\Seeders\MixRadius\VouchersSeeder;
use Illuminate\Database\Seeder;

class MixRadiusImportSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== MixRadius Import Started ===');

        // Register the parser as a singleton so all sub-seeders share the same instance
        app()->singleton(MixRadiusSqlParser::class);

        $this->call([
            UsersSeeder::class,
            NasSeeder::class,
            BandwidthSeeder::class,
            ProfileGroupSeeder::class,
            PlansSeeder::class,
            PppCustomersSeeder::class,
            HotspotCustomersSeeder::class,
            VouchersSeeder::class,
            TransactionsSeeder::class,
        ]);

        $this->command->info('=== MixRadius Import Completed ===');
    }
}
