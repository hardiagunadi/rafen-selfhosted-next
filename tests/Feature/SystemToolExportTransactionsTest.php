<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createInvoiceForExport(User $tenant, string $invoiceNumber): Invoice
{
    $pppUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'username' => 'ppp-'.$invoiceNumber,
        'customer_name' => 'Pelanggan Export',
    ]);

    return Invoice::query()->create([
        'invoice_number' => $invoiceNumber,
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenant->id,
        'customer_id' => 'CUST-EXPORT-001',
        'customer_name' => 'Pelanggan Export',
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket 20 Mbps',
        'harga_dasar' => 100000,
        'ppn_percent' => 11,
        'ppn_amount' => 11000,
        'total' => 111000,
        'status' => 'paid',
        'due_date' => now()->toDateString(),
        'paid_at' => now(),
        'payment_method' => 'transfer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('exports transaksi invoice as csv by default', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $invoice = createInvoiceForExport($tenant, 'INV-EXPORT-CSV-001');

    $response = $this->actingAs($tenant)->get(route('tools.export-transactions.download', [
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
        'status' => 'paid',
    ]));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('.csv');
    expect($response->getContent())->toContain('invoice_number');
    expect($response->getContent())->toContain($invoice->invoice_number);
});

it('exports transaksi invoice as excel when format excel is selected', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $invoice = createInvoiceForExport($tenant, 'INV-EXPORT-XLS-001');

    $response = $this->actingAs($tenant)->get(route('tools.export-transactions.download', [
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
        'status' => 'paid',
        'format' => 'excel',
    ]));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('.xls');
    expect($response->getContent())->toContain('<Workbook');
    expect($response->getContent())->toContain($invoice->invoice_number);
});
