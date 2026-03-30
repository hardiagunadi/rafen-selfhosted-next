<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            $table->string('mac_address', 17)->nullable()->index()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            $table->dropColumn('mac_address');
        });
    }
};
