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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete()->after('paid_at');
            $table->decimal('cash_received', 12, 2)->nullable()->after('paid_by');
            $table->decimal('transfer_amount', 12, 2)->nullable()->after('cash_received');
            $table->text('payment_note')->nullable()->after('transfer_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['paid_by', 'cash_received', 'transfer_amount', 'payment_note']);
        });
    }
};
