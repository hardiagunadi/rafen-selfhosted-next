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
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('business_logo')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
            $table->text('business_address')->nullable();
            $table->string('invoice_prefix')->default('INV');
            $table->text('invoice_footer')->nullable();
            $table->text('invoice_notes')->nullable();
            $table->boolean('enable_qris_payment')->default(false);
            $table->boolean('enable_va_payment')->default(false);
            $table->boolean('enable_manual_payment')->default(true);
            $table->string('tripay_api_key')->nullable();
            $table->string('tripay_private_key')->nullable();
            $table->string('tripay_merchant_code')->nullable();
            $table->boolean('tripay_sandbox')->default(true);
            $table->json('enabled_payment_channels')->nullable();
            $table->integer('payment_expiry_hours')->default(24);
            $table->boolean('auto_isolate_unpaid')->default(true);
            $table->integer('grace_period_days')->default(3);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
