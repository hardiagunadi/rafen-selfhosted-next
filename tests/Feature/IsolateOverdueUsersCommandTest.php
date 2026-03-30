<?php

use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeIsolateOverdueTenant(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

it('isolates overdue unpaid users for tenants with auto isolate enabled', function () {
    $tenant = makeIsolateOverdueTenant();

    TenantSettings::getOrCreate($tenant->id)->update([
        'auto_isolate_unpaid' => true,
    ]);

    $overdueUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000009001',
        'customer_name' => 'Pelanggan Overdue',
        'username' => 'pelanggan-overdue-command',
        'ppp_password' => 'secret',
    ]);

    $futureUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->addDay()->toDateString(),
        'customer_id' => '000000009002',
        'customer_name' => 'Pelanggan Belum Jatuh Tempo',
        'username' => 'pelanggan-future-command',
        'ppp_password' => 'secret',
    ]);

    $radiusSyncMock = Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldReceive('syncSingleUser')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($overdueUser))
        ->andReturnNull();
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldReceive('isolate')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($overdueUser))
        ->andReturnNull();
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $this->artisan('billing:isolate-overdue')
        ->assertExitCode(0);

    expect($overdueUser->fresh()->status_akun)->toBe('isolir')
        ->and($futureUser->fresh()->status_akun)->toBe('enable');
});

it('skips overdue users when tenant disables auto isolate unpaid', function () {
    $tenant = makeIsolateOverdueTenant();

    TenantSettings::getOrCreate($tenant->id)->update([
        'auto_isolate_unpaid' => false,
    ]);

    $user = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000009003',
        'customer_name' => 'Pelanggan Skip',
        'username' => 'pelanggan-skip-command',
        'ppp_password' => 'secret',
    ]);

    $radiusSyncMock = Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldNotReceive('syncSingleUser');
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldNotReceive('isolate');
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $this->artisan('billing:isolate-overdue')
        ->assertExitCode(0);

    expect($user->fresh()->status_akun)->toBe('enable');
});

it('supports dry run without mutating overdue users', function () {
    $tenant = makeIsolateOverdueTenant();

    TenantSettings::getOrCreate($tenant->id)->update([
        'auto_isolate_unpaid' => true,
    ]);

    $user = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000009004',
        'customer_name' => 'Pelanggan Dry Run',
        'username' => 'pelanggan-dry-run-command',
        'ppp_password' => 'secret',
    ]);

    $radiusSyncMock = Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldNotReceive('syncSingleUser');
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldNotReceive('isolate');
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $this->artisan('billing:isolate-overdue --dry-run')
        ->assertExitCode(0);

    expect($user->fresh()->status_akun)->toBe('enable');
});
