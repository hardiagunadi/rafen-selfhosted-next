<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('wa_gateway_key')->nullable()->after('wa_gateway_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('wa_gateway_key');
        });
    }
};
