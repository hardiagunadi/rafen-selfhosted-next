<?php

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns the next available SQL pool IP when creating a PPP user', function () {
    $owner = User::factory()->create([
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $group = ProfileGroup::factory()->create([
        'ip_pool_mode' => 'sql',
        'range_start' => '10.0.0.2',
        'range_end' => '10.0.0.3',
        'host_min' => null,
        'host_max' => null,
    ]);
    $profile = PppProfile::query()->create([
        'name' => 'Paket Basic',
        'owner_id' => $owner->id,
        'profile_group_id' => $group->id,
    ]);

    PppUser::query()->create([
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'sudah_bayar',
        'status_akun' => 'enable',
        'owner_id' => $owner->id,
        'ppp_profile_id' => $profile->id,
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'static',
        'profile_group_id' => $group->id,
        'ip_static' => '10.0.0.2',
        'customer_id' => 'CUST-001',
        'customer_name' => 'User Lama',
        'nik' => '1234567890',
        'nomor_hp' => '081234567890',
        'email' => 'lama@example.test',
        'alamat' => 'Alamat Lama',
        'metode_login' => 'username_password',
        'username' => 'lama',
        'ppp_password' => 'secret',
    ]);

    $this->actingAs($owner)
        ->post(route('ppp-users.store'), [
            'status_registrasi' => 'aktif',
            'tipe_pembayaran' => 'prepaid',
            'status_bayar' => 'sudah_bayar',
            'status_akun' => 'enable',
            'owner_id' => $owner->id,
            'ppp_profile_id' => $profile->id,
            'tipe_service' => 'pppoe',
            'aksi_jatuh_tempo' => 'isolir',
            'tipe_ip' => 'static',
            'profile_group_id' => $group->id,
            'customer_id' => 'CUST-002',
            'customer_name' => 'User Baru',
            'nik' => '9876543210',
            'nomor_hp' => '081298765432',
            'email' => 'baru@example.test',
            'alamat' => 'Alamat Baru',
            'metode_login' => 'username_password',
            'username' => 'baru',
            'ppp_password' => 'secret',
        ])
        ->assertRedirect(route('ppp-users.index'));

    $newUser = PppUser::query()->where('username', 'baru')->firstOrFail();

    expect($newUser->ip_static)->toBe('10.0.0.3');
});

it('rejects PPP user creation when SQL pool is exhausted', function () {
    $owner = User::factory()->create([
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $group = ProfileGroup::factory()->create([
        'ip_pool_mode' => 'sql',
        'range_start' => '10.0.1.2',
        'range_end' => '10.0.1.2',
        'host_min' => null,
        'host_max' => null,
    ]);
    $profile = PppProfile::query()->create([
        'name' => 'Paket Solo',
        'owner_id' => $owner->id,
        'profile_group_id' => $group->id,
    ]);

    PppUser::query()->create([
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'sudah_bayar',
        'status_akun' => 'enable',
        'owner_id' => $owner->id,
        'ppp_profile_id' => $profile->id,
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'static',
        'profile_group_id' => $group->id,
        'ip_static' => '10.0.1.2',
        'customer_id' => 'CUST-003',
        'customer_name' => 'User Solo',
        'nik' => '1111111111',
        'nomor_hp' => '081200000000',
        'email' => 'solo@example.test',
        'alamat' => 'Alamat Solo',
        'metode_login' => 'username_password',
        'username' => 'solo',
        'ppp_password' => 'secret',
    ]);

    $this->actingAs($owner)
        ->post(route('ppp-users.store'), [
            'status_registrasi' => 'aktif',
            'tipe_pembayaran' => 'prepaid',
            'status_bayar' => 'sudah_bayar',
            'status_akun' => 'enable',
            'owner_id' => $owner->id,
            'ppp_profile_id' => $profile->id,
            'tipe_service' => 'pppoe',
            'aksi_jatuh_tempo' => 'isolir',
            'tipe_ip' => 'static',
            'profile_group_id' => $group->id,
            'customer_id' => 'CUST-004',
            'customer_name' => 'User Baru',
            'nik' => '2222222222',
            'nomor_hp' => '081211111111',
            'email' => 'baru2@example.test',
            'alamat' => 'Alamat Baru 2',
            'metode_login' => 'username_password',
            'username' => 'baru2',
            'ppp_password' => 'secret',
        ])
        ->assertSessionHasErrors('ip_static');

    expect(PppUser::query()->where('username', 'baru2')->exists())->toBeFalse();
});
