<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renews unpaid invoice without payment and reactivates isolated internet access', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'isolir',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000000104',
        'customer_name' => 'Pelanggan Renew Uji',
        'username' => 'pelanggan-renew-uji',
    ]);

    $radiusSyncMock = \Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldReceive('syncSingleUser')
        ->once()
        ->with(\Mockery::type(PppUser::class))
        ->andReturnNull();
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = \Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldReceive('deisolate')
        ->once()
        ->with(\Mockery::type(PppUser::class))
        ->andReturnNull();
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-RNW-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Renew',
        'total' => 150000,
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->postJson(route('invoices.renew', $invoice))
        ->assertSuccessful()
        ->assertJsonPath('status', 'Layanan diperpanjang. Status: Aktif - Belum Bayar.');

    $invoice->refresh();
    $pppUser->refresh();

    expect($invoice->status)->toBe('unpaid')
        ->and($invoice->due_date?->isFuture())->toBeTrue()
        ->and($pppUser->status_bayar)->toBe('belum_bayar')
        ->and($pppUser->status_akun)->toBe('enable')
        ->and($pppUser->jatuh_tempo?->toDateString())->toBe($invoice->due_date?->toDateString());
});
