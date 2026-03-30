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
        // Kolom sudah disertakan di migration create (2026_03_15_200001).
        // Migration ini hanya untuk production DB yang dibuat sebelum kolom ditambahkan ke create migration.
        if (! Schema::hasColumn('outages', 'include_status_link')) {
            Schema::table('outages', function (Blueprint $table) {
                $table->boolean('include_status_link')->default(true)->after('wa_blast_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outages', 'include_status_link')) {
            Schema::table('outages', function (Blueprint $table) {
                $table->dropColumn('include_status_link');
            });
        }
    }
};
