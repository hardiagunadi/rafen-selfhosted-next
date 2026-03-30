<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('portal_slug', 80)->nullable()->unique()->after('genieacs_password');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('portal_slug');
        });
    }
};
