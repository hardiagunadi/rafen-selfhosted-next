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
        Schema::create('finance_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category');
            $table->enum('service_type', ['general', 'pppoe', 'hotspot', 'voucher'])->default('general');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'expense_date']);
            $table->index('service_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_expenses');
    }
};
