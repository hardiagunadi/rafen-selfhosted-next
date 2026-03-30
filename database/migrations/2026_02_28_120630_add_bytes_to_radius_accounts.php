<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            $table->bigInteger('bytes_in')->unsigned()->nullable()->after('server_name');
            $table->bigInteger('bytes_out')->unsigned()->nullable()->after('bytes_in');
        });
    }

    public function down(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            $table->dropColumn(['bytes_in', 'bytes_out']);
        });
    }
};
