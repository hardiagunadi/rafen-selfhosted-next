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
        if (Schema::hasColumn('wa_ticket_notes', 'read_by_cs')) {
            return;
        }

        Schema::table('wa_ticket_notes', function (Blueprint $table) {
            // false = note dari teknisi yang belum dibaca CS/NOC
            // default true agar note yang dibuat CS/admin tidak dihitung sebagai notif
            $table->boolean('read_by_cs')->default(true)->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('wa_ticket_notes', function (Blueprint $table) {
            $table->dropColumn('read_by_cs');
        });
    }
};
