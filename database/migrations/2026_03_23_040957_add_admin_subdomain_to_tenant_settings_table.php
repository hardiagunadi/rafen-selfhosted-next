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
            $table->string('admin_subdomain')->nullable()->unique()->after('portal_slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('admin_subdomain');
        });
    }
};
