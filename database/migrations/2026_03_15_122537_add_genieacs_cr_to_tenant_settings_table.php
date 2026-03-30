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
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('genieacs_cr_username')->nullable()->after('genieacs_password');
            $table->string('genieacs_cr_password')->nullable()->after('genieacs_cr_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['genieacs_cr_username', 'genieacs_cr_password']);
        });
    }
};
