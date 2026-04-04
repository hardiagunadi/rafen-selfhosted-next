<?php

use App\Models\MikrotikConnection;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Carbon\Carbon;
use Database\Seeders\MixRadius\MixRadiusSqlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('simulates isolate overdue flow using a real PPP customer from the MixRadius dump', function () {
    $dumpPath = database_path('backup_mixradius.sql');
    expect($dumpPath)->toBeFile();

    $parser = app(MixRadiusSqlParser::class);

    $pppRow = collect($parser->getTableData('tbl_customers'))
        ->first(function (array $row): bool {
            if (strtoupper((string) ($row['type'] ?? '')) !== 'PPP') {
                return false;
            }

            if (($row['auth_status'] ?? null) === 'Disabled-Users') {
                return false;
            }

            return filled($row['username'] ?? null);
        });

    expect($pppRow)->not()->toBeNull();

    $nasRow = collect($parser->getTableData('nas'))
        ->first(fn (array $row): bool => filled($row['nasname'] ?? null));

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'auto_isolate_unpaid' => true,
    ]);

    if ($nasRow !== null) {
        MikrotikConnection::factory()->create([
            'owner_id' => $owner->id,
            'name' => (string) ($nasRow['shortname'] ?? 'Dump NAS'),
            'host' => (string) $nasRow['nasname'],
            'is_active' => true,
            'is_online' => true,
            'isolir_pool_range' => '10.99.0.2-10.99.0.254',
        ]);
    }

    $customerName = trim((string) ($pppRow['fullname'] ?? '')) !== ''
        ? (string) $pppRow['fullname']
        : (string) $pppRow['username'];

    $pppUser = PppUser::query()->create([
        'owner_id' => $owner->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => Carbon::yesterday()->toDateString(),
        'customer_id' => 'DUMP-'.str_pad((string) ($pppRow['id'] ?? '1'), 6, '0', STR_PAD_LEFT),
        'customer_name' => $customerName,
        'username' => (string) $pppRow['username'],
        'ppp_password' => (string) ($pppRow['password'] ?? 'secret-dump'),
        'alamat' => $pppRow['address'] ?? null,
        'nomor_hp' => $pppRow['phonenumber'] ?? null,
        'email' => $pppRow['email'] ?? null,
        'catatan' => 'Simulasi isolir dari dump MixRadius',
    ]);

    $radiusSyncMock = Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldReceive('syncSingleUser')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($pppUser))
        ->andReturnNull();
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldReceive('isolate')
        ->once()
        ->withArgs(fn (PppUser $user): bool => $user->is($pppUser))
        ->andReturnNull();
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $this->artisan('billing:isolate-overdue --owner-id='.$owner->id)
        ->expectsOutputToContain((string) $pppRow['username'])
        ->assertExitCode(0);

    expect($pppUser->fresh()->status_akun)
        ->toBe('isolir');
});
