<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('via_platform_gateway')->default(false)->after('payment_gateway_id')
                ->comment('True when payment used super admin platform gateway');
            $table->decimal('platform_fee_amount', 12, 2)->nullable()->after('via_platform_gateway')
                ->comment('Fee deducted from tenant wallet on this payment');
            $table->decimal('tenant_net_amount', 12, 2)->nullable()->after('platform_fee_amount')
                ->comment('Net amount credited to tenant wallet = total_amount - platform_fee_amount');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('tenant_net_amount')
                ->comment('FK to tenant_wallet_transactions for the credit entry');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'via_platform_gateway',
                'platform_fee_amount',
                'tenant_net_amount',
                'wallet_transaction_id',
            ]);
        });
    }
};
