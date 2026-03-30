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
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('active_gateway')->nullable()->default('tripay')->after('tripay_sandbox');
            // Midtrans
            $table->string('midtrans_server_key')->nullable()->after('active_gateway');
            $table->string('midtrans_client_key')->nullable()->after('midtrans_server_key');
            $table->string('midtrans_merchant_id')->nullable()->after('midtrans_client_key');
            $table->boolean('midtrans_sandbox')->default(true)->after('midtrans_merchant_id');
            // Duitku
            $table->string('duitku_merchant_code')->nullable()->after('midtrans_sandbox');
            $table->string('duitku_api_key')->nullable()->after('duitku_merchant_code');
            $table->boolean('duitku_sandbox')->default(true)->after('duitku_api_key');
            // iPaymu
            $table->string('ipaymu_va')->nullable()->after('duitku_sandbox');
            $table->string('ipaymu_api_key')->nullable()->after('ipaymu_va');
            $table->boolean('ipaymu_sandbox')->default(true)->after('ipaymu_api_key');
            // Xendit
            $table->string('xendit_secret_key')->nullable()->after('ipaymu_sandbox');
            $table->string('xendit_webhook_token')->nullable()->after('xendit_secret_key');
            $table->boolean('xendit_sandbox')->default(true)->after('xendit_webhook_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'active_gateway',
                'midtrans_server_key', 'midtrans_client_key', 'midtrans_merchant_id', 'midtrans_sandbox',
                'duitku_merchant_code', 'duitku_api_key', 'duitku_sandbox',
                'ipaymu_va', 'ipaymu_api_key', 'ipaymu_sandbox',
                'xendit_secret_key', 'xendit_webhook_token', 'xendit_sandbox',
            ]);
        });
    }
};
