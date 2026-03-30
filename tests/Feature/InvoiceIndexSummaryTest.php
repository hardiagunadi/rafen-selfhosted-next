<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows unpaid invoice recap by month on invoice index page', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'customer_id' => '000000009001',
        'customer_name' => 'Pelanggan Rekap',
        'username' => 'pelanggan-rekap',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-SUM-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Maret A',
        'total' => 100000,
        'due_date' => '2026-03-05',
        'status' => 'unpaid',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-SUM-002',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Maret B',
        'total' => 200000,
        'due_date' => '2026-03-15',
        'status' => 'unpaid',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-SUM-003',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket April',
        'total' => 150000,
        'due_date' => '2026-04-10',
        'status' => 'unpaid',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-SUM-004',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Lunas',
        'total' => 500000,
        'due_date' => '2026-04-18',
        'status' => 'paid',
    ]);

    $marchLabel = Carbon::parse('2026-03-05')->translatedFormat('F Y');
    $aprilLabel = Carbon::parse('2026-04-10')->translatedFormat('F Y');

    $this->actingAs($tenantAdmin)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertSee('Rekap Invoice Terhutang per Bulan')
        ->assertSee('Invoice Terhutang')
        ->assertSee('Semua Konteks')
        ->assertSee('Invoice Tunggakan')
        ->assertSee('Perpanjangan Bulan Berjalan')
        ->assertSee('Rp 450.000')
        ->assertSee($marchLabel)
        ->assertSee($aprilLabel)
        ->assertSee('2 invoice')
        ->assertSee('1 invoice')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 150.000')
        ->assertDontSee('Rp 500.000');
});

it('shows dedicated unpaid invoice page without monthly recap section', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'jatuh_tempo' => '2026-04-10',
        'customer_id' => '000000009002',
        'customer_name' => 'Pelanggan Outstanding',
        'username' => 'pelanggan-outstanding',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-UNPAID-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Maret',
        'total' => 125000,
        'due_date' => '2026-03-10',
        'status' => 'unpaid',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-UNPAID-002',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket April',
        'total' => 150000,
        'due_date' => '2026-04-10',
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('invoices.unpaid'))
        ->assertOk()
        ->assertSee('Invoice Belum Lunas')
        ->assertSee('Semua Invoice Belum Lunas')
        ->assertSee('Invoice Tunggakan')
        ->assertSee('Perpanjangan Bulan Berjalan')
        ->assertSee('Konteks aktif:')
        ->assertDontSee('Rekap Invoice Terhutang per Bulan')
        ->assertDontSee('Total Terhutang')
        ->assertDontSee('Bulan Terhutang');
});
