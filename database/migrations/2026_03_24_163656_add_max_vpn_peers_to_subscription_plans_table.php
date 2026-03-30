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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('max_vpn_peers')->default(-1)->comment('-1 = unlimited')->after('max_ppp_users');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('max_vpn_peers');
        });
    }
};
