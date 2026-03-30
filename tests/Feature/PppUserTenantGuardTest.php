<?php

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTenantAdmin(string $email): User
{
    return User::factory()->create([
        'name' => 'Tenant Admin',
        'email' => $email,
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makePppProfileFor(User $owner, string $name): PppProfile
{
    return PppProfile::query()->create([
        'name' => $name,
        'owner_id' => $owner->id,
    ]);
}

function makePppUserFor(User $owner, PppProfile $profile, string $username): PppUser
{
    return PppUser::query()->create([
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'sudah_bayar',
        'status_akun' => 'enable',
        'owner_id' => $owner->id,
        'ppp_profile_id' => $profile->id,
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'customer_id' => fake()->numerify('##########'),
        'customer_name' => 'User '.$username,
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('1########'),
        'email' => $username.'@example.test',
        'alamat' => 'Alamat '.$username,
        'metode_login' => 'username_password',
        'username' => $username,
        'ppp_password' => 'secret123',
    ]);
}

it('forces owner_id to authenticated tenant when storing PPP user', function () {
    $tenantA = makeTenantAdmin('tenant-a@example.test');
    $tenantB = makeTenantAdmin('tenant-b@example.test');
    $profileA = makePppProfileFor($tenantA, 'Paket A');

    $this->actingAs($tenantA)
        ->post(route('ppp-users.store'), [
            'status_registrasi' => 'aktif',
            'tipe_pembayaran' => 'prepaid',
            'status_bayar' => 'sudah_bayar',
            'status_akun' => 'enable',
            'owner_id' => $tenantB->id,
            'ppp_profile_id' => $profileA->id,
            'tipe_service' => 'pppoe',
            'aksi_jatuh_tempo' => 'isolir',
            'tipe_ip' => 'dhcp',
            'customer_id' => 'CUST-GUARD-001',
            'customer_name' => 'Guard User',
            'nik' => fake()->numerify('################'),
            'nomor_hp' => '628'.fake()->unique()->numerify('2########'),
            'email' => 'guard-user@example.test',
            'alamat' => 'Alamat Guard',
            'metode_login' => 'username_password',
            'username' => 'guard-user',
            'ppp_password' => 'secret123',
        ])
        ->assertRedirect(route('ppp-users.index'));

    $created = PppUser::query()->where('username', 'guard-user')->firstOrFail();

    expect($created->owner_id)->toBe($tenantA->id);
});

it('forbids tenant from updating PPP user owned by another tenant', function () {
    $tenantA = makeTenantAdmin('tenant-a-update@example.test');
    $tenantB = makeTenantAdmin('tenant-b-update@example.test');
    $profileB = makePppProfileFor($tenantB, 'Paket B');
    $foreignUser = makePppUserFor($tenantB, $profileB, 'foreign-user');

    $this->actingAs($tenantA)
        ->put(route('ppp-users.update', $foreignUser), [
            'status_registrasi' => 'aktif',
            'tipe_pembayaran' => 'prepaid',
            'status_bayar' => 'sudah_bayar',
            'status_akun' => 'enable',
            'owner_id' => $tenantA->id,
            'ppp_profile_id' => $profileB->id,
            'tipe_service' => 'pppoe',
            'aksi_jatuh_tempo' => 'isolir',
            'tipe_ip' => 'dhcp',
            'customer_id' => $foreignUser->customer_id,
            'customer_name' => 'Hacked Name',
            'nik' => $foreignUser->nik,
            'nomor_hp' => $foreignUser->nomor_hp,
            'email' => $foreignUser->email,
            'alamat' => $foreignUser->alamat,
            'metode_login' => 'username_password',
            'username' => $foreignUser->username,
            'ppp_password' => $foreignUser->ppp_password,
        ])
        ->assertForbidden();

    expect($foreignUser->fresh()->customer_name)->toBe('User foreign-user');
    expect($foreignUser->fresh()->owner_id)->toBe($tenantB->id);
});
