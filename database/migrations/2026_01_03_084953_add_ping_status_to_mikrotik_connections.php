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
            $table->boolean('is_online')->nullable()->after('is_active');
            $table->unsignedInteger('last_ping_latency_ms')->nullable()->after('is_online');
            $table->timestamp('last_ping_at')->nullable()->after('last_ping_latency_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'last_ping_latency_ms', 'last_ping_at']);
        });
    }
};
