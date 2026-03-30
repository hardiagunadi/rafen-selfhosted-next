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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_method', ['monthly', 'license'])
                ->default('monthly')
                ->after('subscription_plan_id');
            $table->integer('license_max_mikrotik')
                ->nullable()
                ->after('subscription_method');
            $table->integer('license_max_ppp_users')
                ->nullable()
                ->after('license_max_mikrotik');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_method',
                'license_max_mikrotik',
                'license_max_ppp_users',
            ]);
        });
    }
};
