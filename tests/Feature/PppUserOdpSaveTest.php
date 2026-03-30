<?php

use App\Models\Odp;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper ─────────────────────────────────────────────────────────────────

function tenantWithOdp(): array
{
    $tenant = User::factory()->create([
        'role'                    => 'administrator',
        'is_super_admin'          => false,
        'subscription_status'     => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $odp = Odp::factory()->create([
        'owner_id' => $tenant->id,
        'name'     => 'ODP Binangun',
        'code'     => 'WNS-BNB-001',
        'area'     => 'Binangun',
    ]);

    $pppUser = PppUser::factory()->create([
        'owner_id'          => $tenant->id,
        'status_akun'       => 'enable',
        'status_registrasi' => 'aktif',
        'tipe_pembayaran'   => 'prepaid',
        'status_bayar'      => 'belum_bayar',
        'tipe_service'      => 'pppoe',
        'tipe_ip'           => 'dhcp',
        'metode_login'      => 'username_password',
        'aksi_jatuh_tempo'  => 'isolir',
        'nomor_hp'          => '628'.fake()->unique()->numerify('1########'),
        'nik'               => fake()->numerify('################'),
        'odp_id'            => null,
    ]);

    return compact('tenant', 'odp', 'pppUser');
}

/** Minimal valid payload untuk update, ambil dari $pppUser */
function updatePayload(PppUser $pppUser, array $override = []): array
{
    return array_merge([
        'customer_name'     => $pppUser->customer_name,
        'username'          => $pppUser->username,
        'ppp_password'      => $pppUser->ppp_password,
        'nomor_hp'          => $pppUser->nomor_hp,
        'email'             => $pppUser->email,
        'alamat'            => $pppUser->alamat,
        'nik'               => $pppUser->nik,
        'customer_id'       => $pppUser->customer_id,
        'status_registrasi' => $pppUser->status_registrasi,
        'tipe_pembayaran'   => $pppUser->tipe_pembayaran,
        'status_bayar'      => $pppUser->status_bayar,
        'status_akun'       => $pppUser->status_akun,
        'aksi_jatuh_tempo'  => $pppUser->aksi_jatuh_tempo,
        'tipe_service'      => $pppUser->tipe_service,
        'tipe_ip'           => $pppUser->tipe_ip,
        'metode_login'      => $pppUser->metode_login,
    ], $override);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('menyimpan odp_id saat user memilih ODP di form edit pelanggan', function () {
    ['tenant' => $tenant, 'odp' => $odp, 'pppUser' => $pppUser] = tenantWithOdp();

    $this->actingAs($tenant)
        ->put(route('ppp-users.update', $pppUser), updatePayload($pppUser, ['odp_id' => $odp->id]))
        ->assertRedirect(route('ppp-users.index'));

    expect($pppUser->fresh()->odp_id)->toBe($odp->id);
});

it('tidak me-reset odp_id yang sudah ada jika field tidak dikirim', function () {
    ['tenant' => $tenant, 'odp' => $odp, 'pppUser' => $pppUser] = tenantWithOdp();

    $pppUser->update(['odp_id' => $odp->id]);

    // Submit tanpa odp_id (simulasi user tidak buka tab Info Pelanggan)
    $this->actingAs($tenant)
        ->put(route('ppp-users.update', $pppUser), updatePayload($pppUser))
        ->assertRedirect(route('ppp-users.index'));

    expect($pppUser->fresh()->odp_id)->toBe($odp->id);
});

it('bisa menghapus odp_id jika dikirim string kosong', function () {
    ['tenant' => $tenant, 'odp' => $odp, 'pppUser' => $pppUser] = tenantWithOdp();

    $pppUser->update(['odp_id' => $odp->id]);

    $this->actingAs($tenant)
        ->put(route('ppp-users.update', $pppUser), updatePayload($pppUser, ['odp_id' => '']))
        ->assertRedirect(route('ppp-users.index'));

    expect($pppUser->fresh()->odp_id)->toBeNull();
});
