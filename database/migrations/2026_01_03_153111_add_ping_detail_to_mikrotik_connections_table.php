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
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->boolean('last_port_open')->nullable()->after('last_ping_latency_ms');
            $table->string('last_ping_message')->nullable()->after('last_port_open');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn(['last_port_open', 'last_ping_message']);
        });
    }
};
