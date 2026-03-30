<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('wa_ticket_group_id')->nullable()->after('wa_shift_group_number')
                ->comment('WhatsApp group JID untuk notifikasi tiket baru');
            $table->string('wa_ticket_group_name')->nullable()->after('wa_ticket_group_id')
                ->comment('Nama grup WA untuk tampilan di UI');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['wa_ticket_group_id', 'wa_ticket_group_name']);
        });
    }
};
