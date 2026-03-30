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
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->unsignedSmallInteger('auth_port')->default(7053)->after('notes');
            $table->unsignedSmallInteger('acct_port')->default(7054)->after('auth_port');
            $table->string('timezone')->default('+07:00 Asia/Jakarta')->after('acct_port');
            $table->string('isolir_url')->nullable()->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn(['auth_port', 'acct_port', 'timezone', 'isolir_url']);
        });
    }
};
