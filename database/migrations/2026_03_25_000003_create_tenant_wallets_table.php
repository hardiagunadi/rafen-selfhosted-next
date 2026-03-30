<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->unique()
                ->comment('FK to users.id — one wallet per tenant admin');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0)
                ->comment('Current available balance in IDR');
            $table->decimal('total_credited', 15, 2)->default(0)
                ->comment('Lifetime total gross amount credited (before fee deduction)');
            $table->decimal('total_withdrawn', 15, 2)->default(0)
                ->comment('Lifetime total amount withdrawn (settled)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_wallets');
    }
};
