<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->string('hotspot_subnet', 64)->nullable()->after('isolir_setup_at')
                  ->comment('Subnet hotspot untuk queue parent, mis: 172.16.0.0/19');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn('hotspot_subnet');
        });
    }
};
