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
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->string('manual_contact_name')->nullable()->after('conversation_id');
            $table->string('manual_contact_phone')->nullable()->after('manual_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->dropColumn(['manual_contact_name', 'manual_contact_phone']);
        });
    }
};
