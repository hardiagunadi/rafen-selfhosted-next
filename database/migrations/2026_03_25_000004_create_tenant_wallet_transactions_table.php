<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index()
                ->comment('Tenant admin user ID');
            $table->enum('type', ['credit', 'debit'])
                ->comment('credit = money in, debit = withdrawal payout');
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_deducted', 15, 2)->default(0)
                ->comment('Platform fee deducted from gross amount (for credit entries)');
            $table->decimal('balance_after', 15, 2)
                ->comment('Wallet balance after this transaction — immutable audit trail');
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable()
                ->comment('e.g. App\\Models\\Payment or App\\Models\\WithdrawalRequest');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_wallet_transactions');
    }
};
