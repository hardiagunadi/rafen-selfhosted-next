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
            $table->foreignId('wa_platform_device_id')
                ->nullable()
                ->after('platform_payment_gateway_id')
                ->constrained('wa_multi_session_devices')
                ->nullOnDelete()
                ->comment('Platform WA device yang di-assign ke tenant ini untuk pengiriman notifikasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropForeign(['wa_platform_device_id']);
            $table->dropColumn('wa_platform_device_id');
        });
    }
};
