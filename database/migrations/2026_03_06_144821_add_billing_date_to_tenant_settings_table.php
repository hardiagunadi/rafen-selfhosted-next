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
        Schema::table('tenant_settings', function (Blueprint $table) {
            // Tanggal penagihan bulanan (1-28). NULL = tidak diset / gunakan jatuh_tempo manual.
            $table->unsignedTinyInteger('billing_date')->nullable()->after('invoice_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('billing_date');
        });
    }
};
