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
        Schema::table('outage_affected_areas', function (Blueprint $table) {
            $table->foreignId('nas_id')
                ->nullable()
                ->after('odp_id')
                ->constrained('mikrotik_connections')
                ->nullOnDelete()
                ->comment('FK ke MikrotikConnection — diisi jika area_type = nas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outage_affected_areas', function (Blueprint $table) {
            $table->dropForeign(['nas_id']);
            $table->dropColumn('nas_id');
        });
    }
};
