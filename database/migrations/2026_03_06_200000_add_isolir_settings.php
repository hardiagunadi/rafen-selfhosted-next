<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant settings: template halaman isolir yang bisa di-edit per tenant
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('isolir_page_title')->nullable()->after('billing_date');
            $table->text('isolir_page_body')->nullable()->after('isolir_page_title');
            $table->string('isolir_page_contact')->nullable()->after('isolir_page_body');
            $table->string('isolir_page_bg_color', 20)->nullable()->after('isolir_page_contact');
            $table->string('isolir_page_accent_color', 20)->nullable()->after('isolir_page_bg_color');
        });

        // MikrotikConnection: track status setup isolir di router ini
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->boolean('isolir_setup_done')->default(false)->after('isolir_url');
            $table->string('isolir_pool_name', 64)->default('pool-isolir')->after('isolir_setup_done');
            $table->string('isolir_pool_range', 64)->default('10.99.0.2-10.99.0.254')->after('isolir_pool_name');
            $table->string('isolir_gateway', 45)->default('10.99.0.1')->after('isolir_pool_range');
            $table->string('isolir_profile_name', 64)->default('isolir-pppoe')->after('isolir_gateway');
            $table->string('isolir_rate_limit', 32)->default('128k/128k')->after('isolir_profile_name');
            $table->timestamp('isolir_setup_at')->nullable()->after('isolir_rate_limit');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'isolir_page_title',
                'isolir_page_body',
                'isolir_page_contact',
                'isolir_page_bg_color',
                'isolir_page_accent_color',
            ]);
        });

        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn([
                'isolir_setup_done',
                'isolir_pool_name',
                'isolir_pool_range',
                'isolir_gateway',
                'isolir_profile_name',
                'isolir_rate_limit',
                'isolir_setup_at',
            ]);
        });
    }
};
