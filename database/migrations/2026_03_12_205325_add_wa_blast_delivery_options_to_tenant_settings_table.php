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
            $table->boolean('wa_blast_multi_device')->default(true)->after('wa_broadcast_enabled');
            $table->boolean('wa_blast_message_variation')->default(true)->after('wa_blast_multi_device');
            $table->unsignedInteger('wa_blast_delay_min_ms')->default(1200)->after('wa_blast_message_variation');
            $table->unsignedInteger('wa_blast_delay_max_ms')->default(2600)->after('wa_blast_delay_min_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'wa_blast_multi_device',
                'wa_blast_message_variation',
                'wa_blast_delay_min_ms',
                'wa_blast_delay_max_ms',
            ]);
        });
    }
};
