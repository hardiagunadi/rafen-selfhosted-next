<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('outage_affected_areas')) {
            return;
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE `outage_affected_areas` MODIFY `area_type` ENUM('odp','keyword','nas') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('outage_affected_areas')) {
            return;
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE `outage_affected_areas` MODIFY `area_type` ENUM('odp','keyword') NOT NULL");
    }
};
