<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('use_platform_gateway')->default(false)->after('active_gateway')
                ->comment('When true, customer payments route through platform PaymentGateway');
            $table->unsignedBigInteger('platform_payment_gateway_id')->nullable()->after('use_platform_gateway')
                ->comment('FK to payment_gateways — which platform gateway to use');
            $table->foreign('platform_payment_gateway_id')
                ->references('id')
                ->on('payment_gateways')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropForeign(['platform_payment_gateway_id']);
            $table->dropColumn(['use_platform_gateway', 'platform_payment_gateway_id']);
        });
    }
};
