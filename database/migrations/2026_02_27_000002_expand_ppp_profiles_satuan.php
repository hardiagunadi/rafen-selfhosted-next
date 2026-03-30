<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ppp_profiles MODIFY satuan ENUM('bulan', 'hari', 'minggu', 'jam', 'menit') NOT NULL DEFAULT 'bulan'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ppp_profiles MODIFY satuan ENUM('bulan') NOT NULL DEFAULT 'bulan'");
    }
};
