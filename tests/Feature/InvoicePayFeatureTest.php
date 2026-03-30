<?php

use App\Jobs\ProcessPaidInvoiceSideEffectsJob;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('dispatches invoice paid side effects after response for ajax pay request', function () {
    Bus::fake();

    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_registrasi' => 'on_process',
        'status_akun' => 'isolir',
        'status_bayar' => 'belum_bayar',
        'customer_id' => '000000000001',
        'customer_name' => 'Pelanggan Uji',
        'username' => 'pelanggan-uji-1',
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-TEST-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Uji',
        'total' => 150000,
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->postJson(route('invoices.pay', $invoice), [
            'cash_received' => 150000,
            'payment_note' => 'lunas tunai',
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'Invoice dibayar.');

    $invoice->refresh();
    $pppUser->refresh();

    expect($invoice->status)->toBe('paid')
        ->and($invoice->paid_by)->toBe($tenantAdmin->id)
        ->and($pppUser->status_bayar)->toBe('sudah_bayar')
        ->and($pppUser->status_akun)->toBe('enable')
        ->and($pppUser->status_registrasi)->toBe('aktif');

    Bus::assertDispatched(ProcessPaidInvoiceSideEffectsJob::class, function (ProcessPaidInvoiceSideEffectsJob $job) use ($invoice, $tenantAdmin, $pppUser): bool {
        return $job->invoiceId === $invoice->id
            && $job->ownerId === $tenantAdmin->id
            && $job->paidByUserId === $tenantAdmin->id
            && $job->pppUserId === $pppUser->id
            && $job->wasOnProcess === true
            && $job->wasIsolir === true
            && $job->hasCashReceived === true;
    });
});

it('returns fast success when invoice already paid and does not dispatch side effects again', function () {
    Bus::fake();

    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'sudah_bayar',
        'customer_id' => '000000000002',
        'customer_name' => 'Pelanggan Uji 2',
        'username' => 'pelanggan-uji-2',
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-TEST-'.now()->format('YmdHis').'02',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Uji',
        'total' => 150000,
        'status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $tenantAdmin->id,
    ]);

    $this->actingAs($tenantAdmin)
        ->postJson(route('invoices.pay', $invoice), [
            'cash_received' => 150000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'Invoice sudah dibayar.');

    Bus::assertNotDispatched(ProcessPaidInvoiceSideEffectsJob::class);
});
