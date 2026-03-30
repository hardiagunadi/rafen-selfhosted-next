<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('genieacs_url')->nullable()->after('xendit_sandbox');
            $table->string('genieacs_username')->nullable()->after('genieacs_url');
            $table->string('genieacs_password')->nullable()->after('genieacs_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['genieacs_url', 'genieacs_username', 'genieacs_password']);
        });
    }
};
