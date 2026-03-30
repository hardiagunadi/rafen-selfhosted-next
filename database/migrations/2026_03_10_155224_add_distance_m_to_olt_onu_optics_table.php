<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('olt_onu_optics', function (Blueprint $table) {
            $table->unsignedInteger('distance_m')->nullable()->after('onu_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_onu_optics', function (Blueprint $table) {
            $table->dropColumn('distance_m');
        });
    }
};
