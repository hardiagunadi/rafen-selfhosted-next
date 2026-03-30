<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->string('mixradius_shortname')->nullable()->unique()->after('notes');
        });

        Schema::table('bandwidth_profiles', function (Blueprint $table) {
            $table->string('mixradius_id')->nullable()->index()->after('owner');
        });

        Schema::table('profile_groups', function (Blueprint $table) {
            $table->string('mixradius_id')->nullable()->index()->after('host_max');
        });

        Schema::table('ppp_profiles', function (Blueprint $table) {
            $table->string('mixradius_id')->nullable()->index()->after('satuan');
        });

        Schema::table('hotspot_profiles', function (Blueprint $table) {
            $table->string('mixradius_id')->nullable()->index()->after('prioritas');
        });

        Schema::table('ppp_users', function (Blueprint $table) {
            $table->string('mixradius_id')->nullable()->index()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropColumn('mixradius_shortname');
        });

        Schema::table('bandwidth_profiles', function (Blueprint $table) {
            $table->dropColumn('mixradius_id');
        });

        Schema::table('profile_groups', function (Blueprint $table) {
            $table->dropColumn('mixradius_id');
        });

        Schema::table('ppp_profiles', function (Blueprint $table) {
            $table->dropColumn('mixradius_id');
        });

        Schema::table('hotspot_profiles', function (Blueprint $table) {
            $table->dropColumn('mixradius_id');
        });

        Schema::table('ppp_users', function (Blueprint $table) {
            $table->dropColumn('mixradius_id');
        });
    }
};
