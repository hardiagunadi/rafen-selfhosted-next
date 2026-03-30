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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_proof')->nullable()->after('notes');
            $table->string('bank_account_id')->nullable()->after('payment_proof');
            $table->decimal('amount_transferred', 15, 2)->nullable()->after('bank_account_id');
            $table->date('transfer_date')->nullable()->after('amount_transferred');
            $table->string('rejection_reason')->nullable()->after('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_proof', 'bank_account_id', 'amount_transferred', 'transfer_date', 'rejection_reason']);
        });
    }
};
