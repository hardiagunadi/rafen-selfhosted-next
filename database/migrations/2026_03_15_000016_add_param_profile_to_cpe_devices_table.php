<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            $table->string('param_profile')->default('igd')->after('genieacs_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('cpe_devices', function (Blueprint $table) {
            $table->dropColumn('param_profile');
        });
    }
};
