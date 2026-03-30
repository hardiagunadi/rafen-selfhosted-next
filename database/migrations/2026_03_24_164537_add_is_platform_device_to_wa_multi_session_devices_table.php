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
        Schema::table('wa_multi_session_devices', function (Blueprint $table) {
            $table->boolean('is_platform_device')->default(false)->after('is_default')
                ->comment('Jika true, device ini digunakan untuk notifikasi platform oleh super admin');
        });
    }

    public function down(): void
    {
        Schema::table('wa_multi_session_devices', function (Blueprint $table) {
            $table->dropColumn('is_platform_device');
        });
    }
};
