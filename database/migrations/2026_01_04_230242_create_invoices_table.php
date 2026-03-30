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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('ppp_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppp_profile_id')->nullable()->constrained('ppp_profiles')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('tipe_service')->nullable();
            $table->string('paket_langganan')->nullable();
            $table->decimal('harga_dasar', 12, 2)->default(0);
            $table->decimal('ppn_percent', 5, 2)->default(0);
            $table->decimal('ppn_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('promo_applied')->default(false);
            $table->date('due_date')->nullable();
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
