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
        if (! Schema::hasTable('olt_connections')) {
            return;
        }

        if (! Schema::hasColumn('olt_connections', 'snmp_write_community')) {
            Schema::table('olt_connections', function (Blueprint $table): void {
                $table->string('snmp_write_community', 191)->nullable()->after('snmp_community');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('olt_connections')) {
            return;
        }

        if (Schema::hasColumn('olt_connections', 'snmp_write_community')) {
            Schema::table('olt_connections', function (Blueprint $table): void {
                $table->dropColumn('snmp_write_community');
            });
        }
    }
};
