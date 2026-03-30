<?php

use App\Models\HotspotUser;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('hotspot_users');
    Schema::dropIfExists('ppp_users');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->default('administrator');
        $table->boolean('is_super_admin')->default(false);
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('ppp_users', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->string('customer_name')->nullable();
        $table->string('customer_id')->nullable();
        $table->string('username')->nullable();
        $table->string('nomor_hp')->nullable();
        $table->string('status_akun')->nullable();
        $table->string('status_bayar')->nullable();
        $table->unsignedBigInteger('ppp_profile_id')->nullable();
        $table->unsignedBigInteger('profile_group_id')->nullable();
        $table->unsignedBigInteger('odp_id')->nullable();
        $table->timestamps();
    });

    Schema::create('hotspot_users', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->string('customer_name')->nullable();
        $table->string('customer_id')->nullable();
        $table->string('username')->nullable();
        $table->string('nomor_hp')->nullable();
        $table->string('status_akun')->nullable();
        $table->string('status_bayar')->nullable();
        $table->unsignedBigInteger('hotspot_profile_id')->nullable();
        $table->unsignedBigInteger('profile_group_id')->nullable();
        $table->timestamps();
    });
});

it('previews recipients based on selected customer keys', function () {
    $this->withoutMiddleware();

    $user = User::query()->create([
        'name' => 'Admin Tenant',
        'email' => 'admin@test.local',
        'password' => bcrypt('secret'),
        'role' => 'administrator',
        'is_super_admin' => false,
    ]);

    $pppSelected = PppUser::query()->create([
        'owner_id' => $user->id,
        'customer_name' => 'Pelanggan PPP Pilihan',
        'customer_id' => 'PPP-001',
        'username' => 'ppp-pilihan',
        'nomor_hp' => '628111111111',
        'status_akun' => 'enable',
        'status_bayar' => 'sudah_bayar',
    ]);

    PppUser::query()->create([
        'owner_id' => $user->id,
        'customer_name' => 'Pelanggan PPP Lain',
        'customer_id' => 'PPP-002',
        'username' => 'ppp-lain',
        'nomor_hp' => '628122222222',
        'status_akun' => 'enable',
        'status_bayar' => 'sudah_bayar',
    ]);

    HotspotUser::query()->create([
        'owner_id' => $user->id,
        'customer_name' => 'Pelanggan Hotspot',
        'customer_id' => 'HS-001',
        'username' => 'hotspot-1',
        'nomor_hp' => '628133333333',
        'status_akun' => 'enable',
        'status_bayar' => 'sudah_bayar',
    ]);

    $response = $this->actingAs($user)->getJson(route('wa-blast.preview', [
        'tipe' => 'all',
        'status_akun' => '',
        'status_bayar' => '',
        'profile_id' => '',
        'recipient_keys' => ['ppp:'.$pppSelected->id],
    ]));

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('phones.0', '628111111111');
});
