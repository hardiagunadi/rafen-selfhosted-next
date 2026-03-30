<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_connections', function (Blueprint $table): void {
            $table->string('cli_protocol', 10)->default('none')->after('snmp_write_community');
            $table->unsignedSmallInteger('cli_port')->nullable()->after('cli_protocol');
            $table->string('cli_username')->nullable()->after('cli_port');
            $table->text('cli_password')->nullable()->after('cli_username');
        });
    }

    public function down(): void
    {
        Schema::table('olt_connections', function (Blueprint $table): void {
            $table->dropColumn(['cli_protocol', 'cli_port', 'cli_username', 'cli_password']);
        });
    }
};
