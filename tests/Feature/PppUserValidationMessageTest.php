<?php

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeValidationTenantAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeValidationProfile(User $owner): PppProfile
{
    return PppProfile::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Paket Validasi',
        'harga_modal' => 150000,
        'harga_promo' => 150000,
        'ppn' => 11,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
    ]);
}

function makeValidationPayload(User $tenant, PppProfile $profile, array $override = []): array
{
    return array_merge([
        'owner_id' => $tenant->id,
        'ppp_profile_id' => $profile->id,
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'belum_bayar',
        'status_akun' => 'enable',
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'customer_id' => 'CUST-VALIDASI-001',
        'customer_name' => 'Pelanggan Validasi',
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('5########'),
        'email' => 'pelanggan-validasi@example.test',
        'alamat' => 'Alamat Validasi',
        'metode_login' => 'username_password',
        'username' => 'pelanggan-validasi',
        'ppp_password' => 'secret123',
    ], $override);
}

it('shows friendly validation messages when updating a PPP customer', function () {
    $tenant = makeValidationTenantAdmin();
    $profile = makeValidationProfile($tenant);

    PppUser::query()->create(makeValidationPayload($tenant, $profile, [
        'customer_id' => 'CUST-VALIDASI-002',
        'customer_name' => 'Pelanggan Existing',
        'username' => 'pelanggan-existing',
        'email' => 'pelanggan-existing@example.test',
        'nomor_hp' => '6200000000',
    ]));

    $pppUser = PppUser::query()->create(makeValidationPayload($tenant, $profile));

    $this->from(route('ppp-users.edit', $pppUser))
        ->actingAs($tenant)
        ->put(route('ppp-users.update', $pppUser), makeValidationPayload($tenant, $profile, [
            'customer_id' => $pppUser->customer_id,
            'customer_name' => $pppUser->customer_name,
            'username' => $pppUser->username,
            'email' => $pppUser->email,
            'nik' => '',
            'alamat' => '',
            'nomor_hp' => '6200000000',
        ]))
        ->assertRedirect(route('ppp-users.edit', $pppUser))
        ->assertSessionHasErrors(['nik', 'alamat', 'nomor_hp']);

    $errors = session('errors');

    expect($errors->first('nik'))->toBe('No. NIK wajib diisi.')
        ->and($errors->first('alamat'))->toBe('Alamat wajib diisi.')
        ->and($errors->first('nomor_hp'))->toBe('Nomor HP ini sudah digunakan oleh pelanggan lain. Setiap pelanggan harus memiliki nomor HP yang unik agar bisa login ke portal.');
});

it('shows friendly validation messages when creating a PPP customer', function () {
    $tenant = makeValidationTenantAdmin();
    $profile = makeValidationProfile($tenant);

    PppUser::query()->create(makeValidationPayload($tenant, $profile, [
        'customer_id' => 'CUST-VALIDASI-003',
        'customer_name' => 'Pelanggan Existing',
        'username' => 'pelanggan-existing-create',
        'email' => 'pelanggan-existing-create@example.test',
        'nomor_hp' => '6200000000',
    ]));

    $this->from(route('ppp-users.create'))
        ->actingAs($tenant)
        ->post(route('ppp-users.store'), makeValidationPayload($tenant, $profile, [
            'nik' => '',
            'alamat' => '',
            'nomor_hp' => '6200000000',
        ]))
        ->assertRedirect(route('ppp-users.create'))
        ->assertSessionHasErrors(['nik', 'alamat', 'nomor_hp']);

    $errors = session('errors');

    expect($errors->first('nik'))->toBe('No. NIK wajib diisi.')
        ->and($errors->first('alamat'))->toBe('Alamat wajib diisi.')
        ->and($errors->first('nomor_hp'))->toBe('Nomor HP ini sudah digunakan oleh pelanggan lain. Setiap pelanggan harus memiliki nomor HP yang unik agar bisa login ke portal.');
});

it('allows username equals password without manually filling password fields', function () {
    $tenant = makeValidationTenantAdmin();
    $profile = makeValidationProfile($tenant);

    $this->actingAs($tenant)
        ->post(route('ppp-users.store'), makeValidationPayload($tenant, $profile, [
            'customer_id' => 'CUST-VALIDASI-004',
            'metode_login' => 'username_equals_password',
            'username' => 'pelanggan-sama-password',
            'ppp_password' => '',
            'password_clientarea' => '',
        ]))
        ->assertRedirect(route('ppp-users.index'))
        ->assertSessionDoesntHaveErrors();

    $pppUser = PppUser::query()
        ->where('username', 'pelanggan-sama-password')
        ->firstOrFail();

    expect($pppUser->ppp_password)->toBe('pelanggan-sama-password')
        ->and($pppUser->password_clientarea)->toBe('pelanggan-sama-password');
});
