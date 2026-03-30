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
            $table->string('payment_method')->nullable()->after('status');
            $table->string('payment_channel')->nullable()->after('payment_method');
            $table->string('payment_reference')->nullable()->after('payment_channel');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
            $table->foreignId('payment_id')->nullable()->after('paid_at')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn([
                'payment_method',
                'payment_channel',
                'payment_reference',
                'paid_at',
                'payment_id',
            ]);
        });
    }
};
