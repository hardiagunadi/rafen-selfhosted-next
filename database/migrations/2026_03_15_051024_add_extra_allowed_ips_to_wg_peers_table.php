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
        Schema::table('wg_peers', function (Blueprint $table) {
            // Comma-separated extra subnets routed via this peer, e.g. "172.17.0.0/16,192.168.0.0/24"
            $table->string('extra_allowed_ips')->nullable()->after('vpn_ip');
        });
    }

    public function down(): void
    {
        Schema::table('wg_peers', function (Blueprint $table) {
            $table->dropColumn('extra_allowed_ips');
        });
    }
};
