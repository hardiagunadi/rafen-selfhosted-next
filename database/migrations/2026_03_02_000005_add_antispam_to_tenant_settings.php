<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('wa_antispam_enabled')->default(true)->after('wa_broadcast_enabled');
            $table->unsignedSmallInteger('wa_antispam_delay_ms')->default(2000)->after('wa_antispam_enabled');
            $table->unsignedSmallInteger('wa_antispam_max_per_minute')->default(10)->after('wa_antispam_delay_ms');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'wa_antispam_enabled',
                'wa_antispam_delay_ms',
                'wa_antispam_max_per_minute',
            ]);
        });
    }
};
