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
        Schema::table('cpe_devices', function (Blueprint $table) {
            $table->foreignId('olt_onu_optic_id')->nullable()->after('cached_params')->constrained('olt_onu_optics')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            $table->dropForeign(['olt_onu_optic_id']);
            $table->dropColumn('olt_onu_optic_id');
        });
    }
};
