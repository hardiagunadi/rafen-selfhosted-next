<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            $table->dropUnique('radius_accounts_username_unique');
            $table->unique(['mikrotik_connection_id', 'username', 'service'], 'radius_accounts_conn_user_service_unique');
        });
    }

    public function down(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            $table->dropUnique('radius_accounts_conn_user_service_unique');
            $table->unique('username');
        });
    }
};
