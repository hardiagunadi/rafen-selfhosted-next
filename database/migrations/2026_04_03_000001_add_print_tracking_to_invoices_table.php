<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('nota_printed_at')->nullable()->after('payment_token');
            $table->foreignId('nota_printed_by')->nullable()->constrained('users')->nullOnDelete()->after('nota_printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['nota_printed_by']);
            $table->dropColumn(['nota_printed_at', 'nota_printed_by']);
        });
    }
};
