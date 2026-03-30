<?php

use App\Models\Invoice;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDueDateTenantAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeDueDateProfile(User $owner): PppProfile
{
    return PppProfile::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Paket Home Fiber',
        'harga_modal' => 150000,
        'harga_promo' => 150000,
        'ppn' => 11,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
    ]);
}

function makeDueDatePayload(PppUser $pppUser, array $override = []): array
{
    return array_merge([
        'customer_name' => $pppUser->customer_name,
        'username' => $pppUser->username,
        'ppp_password' => $pppUser->ppp_password,
        'nomor_hp' => $pppUser->nomor_hp,
        'email' => $pppUser->email,
        'alamat' => $pppUser->alamat,
        'nik' => $pppUser->nik,
        'customer_id' => $pppUser->customer_id,
        'status_registrasi' => $pppUser->status_registrasi,
        'tipe_pembayaran' => $pppUser->tipe_pembayaran,
        'status_bayar' => $pppUser->status_bayar,
        'status_akun' => $pppUser->status_akun,
        'aksi_jatuh_tempo' => $pppUser->aksi_jatuh_tempo,
        'tipe_service' => $pppUser->tipe_service,
        'tipe_ip' => $pppUser->tipe_ip,
        'metode_login' => $pppUser->metode_login,
        'ppp_profile_id' => $pppUser->ppp_profile_id,
        'jatuh_tempo' => optional($pppUser->jatuh_tempo)->toDateString(),
    ], $override);
}

it('automatically isolates a customer when due date is manually moved backward past today', function () {
    $tenant = makeDueDateTenantAdmin();
    $profile = makeDueDateProfile($tenant);

    TenantSettings::getOrCreate($tenant->id)->update([
        'auto_isolate_unpaid' => true,
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'ppp_profile_id' => $profile->id,
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'belum_bayar',
        'status_akun' => 'enable',
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'metode_login' => 'username_password',
        'customer_id' => 'CUST-DUE-001',
        'customer_name' => 'Pelanggan Mundur',
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('1########'),
        'email' => 'pelanggan-mundur@example.test',
        'alamat' => 'Alamat Mundur',
        'username' => 'pelanggan-mundur',
        'ppp_password' => 'secret123',
        'jatuh_tempo' => now()->addDays(7)->toDateString(),
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-DUE-BACK-001',
        'ppp_user_id' => $pppUser->id,
        'ppp_profile_id' => $profile->id,
        'owner_id' => $tenant->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => $profile->name,
        'harga_dasar' => 150000,
        'total' => 150000,
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => 'unpaid',
    ]);

    $radiusSyncMock = Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldReceive('syncSingleUser')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($pppUser) && $user->status_akun === 'isolir')
        ->andReturnNull();
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldReceive('isolate')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($pppUser) && $user->status_akun === 'isolir')
        ->andReturnNull();
    $isolirSyncMock->shouldNotReceive('deisolate');
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $this->actingAs($tenant)
        ->put(route('ppp-users.update', $pppUser), makeDueDatePayload($pppUser, [
            'jatuh_tempo' => now()->subDay()->toDateString(),
        ]))
        ->assertRedirect(route('ppp-users.index'));

    $pppUser->refresh();
    $latestInvoice = $pppUser->invoices()->where('status', 'unpaid')->latest('due_date')->first();

    expect($pppUser->status_akun)->toBe('isolir')
        ->and($pppUser->jatuh_tempo?->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($latestInvoice?->due_date?->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($pppUser->invoices()->where('status', 'unpaid')->count())->toBe(1);
});

it('automatically extends due date forward and releases isolation when due date is manually moved ahead', function () {
    $tenant = makeDueDateTenantAdmin();
    $profile = makeDueDateProfile($tenant);

    TenantSettings::getOrCreate($tenant->id)->update([
        'auto_isolate_unpaid' => true,
    ]);

    $oldDueDate = now()->subDay()->toDateString();
    $newDueDate = now()->addDays(10)->toDateString();

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'ppp_profile_id' => $profile->id,
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'belum_bayar',
        'status_akun' => 'isolir',
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'metode_login' => 'username_password',
        'customer_id' => 'CUST-DUE-002',
        'customer_name' => 'Pelanggan Maju',
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('2########'),
        'email' => 'pelanggan-maju@example.test',
        'alamat' => 'Alamat Maju',
        'username' => 'pelanggan-maju',
        'ppp_password' => 'secret123',
        'jatuh_tempo' => $oldDueDate,
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-DUE-FWD-001',
        'ppp_user_id' => $pppUser->id,
        'ppp_profile_id' => $profile->id,
        'owner_id' => $tenant->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => $profile->name,
        'harga_dasar' => 150000,
        'total' => 150000,
        'due_date' => $oldDueDate,
        'status' => 'unpaid',
    ]);

    $radiusSyncMock = Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldReceive('syncSingleUser')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($pppUser) && $user->status_akun === 'enable')
        ->andReturnNull();
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldReceive('deisolate')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($pppUser) && $user->status_akun === 'enable')
        ->andReturnNull();
    $isolirSyncMock->shouldNotReceive('isolate');
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $this->actingAs($tenant)
        ->put(route('ppp-users.update', $pppUser), makeDueDatePayload($pppUser, [
            'jatuh_tempo' => $newDueDate,
        ]))
        ->assertRedirect(route('ppp-users.index'));

    $pppUser->refresh();
    $latestInvoice = $pppUser->invoices()->where('status', 'unpaid')->latest('due_date')->first();

    expect($pppUser->status_akun)->toBe('enable')
        ->and($pppUser->jatuh_tempo?->toDateString())->toBe($newDueDate)
        ->and($latestInvoice?->due_date?->toDateString())->toBe($newDueDate)
        ->and($pppUser->invoices()->where('status', 'unpaid')->count())->toBe(1);
});

it('shows isolir as an editable status option for isolated customers', function () {
    $tenant = makeDueDateTenantAdmin();
    $profile = makeDueDateProfile($tenant);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'ppp_profile_id' => $profile->id,
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'belum_bayar',
        'status_akun' => 'isolir',
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'metode_login' => 'username_password',
        'customer_id' => 'CUST-DUE-003',
        'customer_name' => 'Pelanggan Form Isolir',
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('3########'),
        'email' => 'pelanggan-form-isolir@example.test',
        'alamat' => 'Alamat Form',
        'username' => 'pelanggan-form-isolir',
        'ppp_password' => 'secret123',
        'jatuh_tempo' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($tenant)
        ->get(route('ppp-users.edit', $pppUser))
        ->assertOk()
        ->assertSee('option value="isolir"', false)
        ->assertSee('value="isolir" selected', false);
});
