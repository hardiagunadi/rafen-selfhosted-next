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
            // Harga penuh sebelum prorata (untuk referensi di tampilan invoice)
            $table->decimal('harga_asli', 12, 2)->default(0)->after('harga_dasar');
            // Apakah invoice ini menggunakan perhitungan prorata
            $table->boolean('prorata_applied')->default(false)->after('promo_applied');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['harga_asli', 'prorata_applied']);
        });
    }
};
