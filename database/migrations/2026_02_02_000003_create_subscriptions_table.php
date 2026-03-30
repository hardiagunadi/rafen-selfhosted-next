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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
