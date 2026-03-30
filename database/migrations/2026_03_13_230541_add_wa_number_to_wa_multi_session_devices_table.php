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
            $table->string('wa_number', 30)->nullable()->after('session_id')
                ->comment('Nomor WA gateway (format 628xxx), untuk matching webhook payload');
            $table->index('wa_number');
        });
    }

    public function down(): void
    {
        Schema::table('wa_multi_session_devices', function (Blueprint $table) {
            $table->dropIndex(['wa_multi_session_devices_wa_number_index']);
            $table->dropColumn('wa_number');
        });
    }
};
