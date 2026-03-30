<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['ppp', 'hotspot', 'voucher'])->default('ppp');
            $table->string('username')->nullable()->index();
            $table->string('plan_name')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['paid', 'unpaid', 'cancelled'])->default('paid');
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('mixradius_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
