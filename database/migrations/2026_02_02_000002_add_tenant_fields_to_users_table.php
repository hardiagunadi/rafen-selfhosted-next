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
            $table->string('phone')->nullable()->after('email');
            $table->string('company_name')->nullable()->after('phone');
            $table->text('address')->nullable()->after('company_name');
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->enum('subscription_status', ['trial', 'active', 'expired', 'suspended'])->default('trial')->after('is_super_admin');
            $table->date('subscription_expires_at')->nullable()->after('subscription_status');
            $table->foreignId('subscription_plan_id')->nullable()->after('subscription_expires_at')->constrained('subscription_plans')->nullOnDelete();
            $table->string('vpn_username')->nullable()->after('subscription_plan_id');
            $table->string('vpn_password')->nullable()->after('vpn_username');
            $table->string('vpn_ip')->nullable()->after('vpn_password');
            $table->boolean('vpn_enabled')->default(false)->after('vpn_ip');
            $table->integer('trial_days_remaining')->default(14)->after('vpn_enabled');
            $table->timestamp('registered_at')->nullable()->after('trial_days_remaining');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn([
                'phone',
                'company_name',
                'address',
                'is_super_admin',
                'subscription_status',
                'subscription_expires_at',
                'subscription_plan_id',
                'vpn_username',
                'vpn_password',
                'vpn_ip',
                'vpn_enabled',
                'trial_days_remaining',
                'registered_at',
            ]);
        });
    }
};
