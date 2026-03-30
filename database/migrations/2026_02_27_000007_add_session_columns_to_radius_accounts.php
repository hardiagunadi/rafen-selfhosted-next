<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            $table->string('uptime', 30)->nullable()->after('is_active');
            $table->string('caller_id', 50)->nullable()->after('uptime');
            $table->string('server_name', 100)->nullable()->after('caller_id');
        });
    }

    public function down(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            $table->dropColumn(['uptime', 'caller_id', 'server_name']);
        });
    }
};
