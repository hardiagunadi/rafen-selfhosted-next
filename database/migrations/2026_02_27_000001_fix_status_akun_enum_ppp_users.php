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

        DB::statement("ALTER TABLE ppp_users MODIFY status_akun ENUM('enable', 'disable', 'isolir') NOT NULL DEFAULT 'enable'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ppp_users MODIFY status_akun ENUM('enable', 'disable') NOT NULL DEFAULT 'enable'");
    }
};
