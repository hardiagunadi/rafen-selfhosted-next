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

        if (! Schema::hasColumn('olt_connections', 'olt_model')) {
            Schema::table('olt_connections', function (Blueprint $table): void {
                $table->string('olt_model', 120)->nullable()->after('name');
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

        if (Schema::hasColumn('olt_connections', 'olt_model')) {
            Schema::table('olt_connections', function (Blueprint $table): void {
                $table->dropColumn('olt_model');
            });
        }
    }
};
