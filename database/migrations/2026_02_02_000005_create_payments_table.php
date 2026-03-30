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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->enum('payment_type', ['subscription', 'invoice'])->default('invoice');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_channel')->nullable()->comment('QRIS, BRIVA, BCAVA, etc.');
            $table->string('payment_method')->nullable()->comment('qris, virtual_account, bank_transfer');
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->string('reference')->nullable()->comment('Gateway transaction reference');
            $table->string('merchant_ref')->nullable()->comment('Our internal reference');
            $table->string('checkout_url')->nullable();
            $table->string('qr_url')->nullable();
            $table->string('qr_string')->nullable();
            $table->string('pay_code')->nullable()->comment('VA number or payment code');
            $table->json('payment_instructions')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('callback_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payment_type', 'status']);
            $table->index('reference');
            $table->index('merchant_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
