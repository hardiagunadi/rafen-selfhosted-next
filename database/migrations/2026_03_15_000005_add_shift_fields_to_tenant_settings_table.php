<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('shift_feature_enabled')->default(false)->after('module_hotspot_enabled');
            $table->string('wa_shift_group_number', 30)->nullable()->after('shift_feature_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['shift_feature_enabled', 'wa_shift_group_number']);
        });
    }
};
