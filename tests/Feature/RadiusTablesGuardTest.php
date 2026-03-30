<?php

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\User;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps radius synchronization working when radcheck and radreply tables exist', function () {
    Schema::create('radcheck', function (Blueprint $table) {
        $table->increments('id');
        $table->string('username');
        $table->string('attribute');
        $table->string('op', 2)->default(':=');
        $table->text('value')->nullable();
    });

    Schema::create('radreply', function (Blueprint $table) {
        $table->increments('id');
        $table->string('username');
        $table->string('attribute');
        $table->string('op', 2)->default(':=');
        $table->text('value')->nullable();
    });

    $owner = User::factory()->create([
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $group = ProfileGroup::factory()->create([
        'name' => 'GROUP-A',
        'ip_pool_mode' => 'group_only',
        'ip_pool_name' => 'POOL-A',
    ]);

    $profile = PppProfile::query()->create([
        'name' => 'Paket A',
        'owner_id' => $owner->id,
        'profile_group_id' => $group->id,
    ]);

    $pppUser = PppUser::query()->create([
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'sudah_bayar',
        'status_akun' => 'enable',
        'owner_id' => $owner->id,
        'ppp_profile_id' => $profile->id,
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'profile_group_id' => $group->id,
        'customer_id' => 'CUST-RADIUS-001',
        'customer_name' => 'User Radius',
        'nik' => '3201010101010001',
        'nomor_hp' => '081234567890',
        'email' => 'radius-user@example.test',
        'alamat' => 'Alamat Radius',
        'metode_login' => 'username_password',
        'username' => 'radius-user',
        'ppp_password' => 'secret',
    ]);

    app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

    $this->assertDatabaseHas('radcheck', [
        'username' => 'radius-user',
        'attribute' => 'Cleartext-Password',
        'op' => ':=',
        'value' => 'secret',
    ]);

    $this->assertDatabaseHas('radreply', [
        'username' => 'radius-user',
        'attribute' => 'Mikrotik-Group',
        'op' => ':=',
        'value' => 'GROUP-A',
    ]);

    $this->assertDatabaseHas('radreply', [
        'username' => 'radius-user',
        'attribute' => 'Framed-Pool',
        'op' => ':=',
        'value' => 'POOL-A',
    ]);
});

it('still applies unique constraints migration on radius tables when they are present', function () {
    Schema::create('radcheck', function (Blueprint $table) {
        $table->increments('id');
        $table->string('username');
        $table->string('attribute');
        $table->string('op', 2)->default(':=');
        $table->text('value')->nullable();
    });

    Schema::create('radreply', function (Blueprint $table) {
        $table->increments('id');
        $table->string('username');
        $table->string('attribute');
        $table->string('op', 2)->default(':=');
        $table->text('value')->nullable();
    });

    DB::table('radcheck')->insert([
        ['username' => 'dup-user', 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => 'a'],
        ['username' => 'dup-user', 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => 'b'],
    ]);

    DB::table('radreply')->insert([
        ['username' => 'dup-user', 'attribute' => 'Mikrotik-Group', 'op' => ':=', 'value' => 'GROUP-1'],
        ['username' => 'dup-user', 'attribute' => 'Mikrotik-Group', 'op' => ':=', 'value' => 'GROUP-2'],
    ]);

    $migration = require database_path('migrations/2026_03_06_220000_add_unique_index_to_radcheck_radreply.php');
    $migration->up();

    expect(DB::table('radcheck')->where('username', 'dup-user')->where('attribute', 'Cleartext-Password')->count())->toBe(1);
    expect(DB::table('radreply')->where('username', 'dup-user')->where('attribute', 'Mikrotik-Group')->count())->toBe(1);

    expect(function () {
        DB::table('radcheck')->insert([
            'username' => 'dup-user',
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => 'c',
        ]);
    })->toThrow(QueryException::class);

    expect(function () {
        DB::table('radreply')->insert([
            'username' => 'dup-user',
            'attribute' => 'Mikrotik-Group',
            'op' => ':=',
            'value' => 'GROUP-3',
        ]);
    })->toThrow(QueryException::class);
});
