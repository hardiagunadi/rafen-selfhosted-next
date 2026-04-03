<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('self_hosted_update_states', function (Blueprint $table) {
            $table->timestamp('last_successful_heartbeat_at')->nullable()->after('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('self_hosted_update_states', function (Blueprint $table) {
            $table->dropColumn('last_successful_heartbeat_at');
        });
    }
};
