<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->boolean('ping_unstable')->default(false)->after('failed_ping_count');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn('ping_unstable');
        });
    }
};
