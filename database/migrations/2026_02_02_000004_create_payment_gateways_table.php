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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider')->comment('tripay, midtrans, xendit, etc.');
            $table->string('api_key')->nullable();
            $table->string('private_key')->nullable();
            $table->string('merchant_code')->nullable();
            $table->string('callback_url')->nullable();
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_active')->default(false);
            $table->json('supported_channels')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
