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
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->string('odp_pop')->nullable()->after('ip_static');
            $table->string('customer_id')->nullable()->after('odp_pop');
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('nik')->nullable()->after('customer_name');
            $table->string('nomor_hp')->nullable()->after('nik');
            $table->string('email')->nullable()->after('nomor_hp');
            $table->text('alamat')->nullable()->after('email');
            $table->string('latitude')->nullable()->after('alamat');
            $table->string('longitude')->nullable()->after('latitude');
            $table->enum('metode_login', ['username_password', 'username_equals_password'])->default('username_password')->after('longitude');
            $table->string('username')->nullable()->after('metode_login');
            $table->string('password_clientarea')->nullable()->after('username');
            $table->text('catatan')->nullable()->after('password_clientarea');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->dropColumn([
                'odp_pop',
                'customer_id',
                'customer_name',
                'nik',
                'nomor_hp',
                'email',
                'alamat',
                'latitude',
                'longitude',
                'metode_login',
                'username',
                'password_clientarea',
                'catatan',
            ]);
        });
    }
};
