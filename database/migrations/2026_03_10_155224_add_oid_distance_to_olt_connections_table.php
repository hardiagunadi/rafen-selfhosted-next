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
        Schema::table('olt_connections', function (Blueprint $table) {
            $table->string('oid_distance')->nullable()->after('oid_tx_olt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_connections', function (Blueprint $table) {
            $table->dropColumn('oid_distance');
        });
    }
};
