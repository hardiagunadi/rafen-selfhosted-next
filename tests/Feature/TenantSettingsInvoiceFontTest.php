<?php

use App\Models\Invoice;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates browser invoice font from tenant business settings', function () {
    $tenant = makeInvoiceFontTenant();

    $this->actingAs($tenant)
        ->put(route('tenant-settings.update-business'), [
            'business_name' => 'ISP Font Browser',
            'browser_invoice_font' => TenantSettings::BROWSER_INVOICE_FONT_ROMAN,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Pengaturan bisnis berhasil diperbarui.');

    expect($tenant->getSettings()->fresh()->browser_invoice_font)
        ->toBe(TenantSettings::BROWSER_INVOICE_FONT_ROMAN);
});

it('renders selected browser font in nota and invoice print views', function () {
    $tenant = makeInvoiceFontTenant();

    TenantSettings::getOrCreate($tenant->id)->update([
        'business_name' => 'ISP Font Browser',
        'browser_invoice_font' => TenantSettings::BROWSER_INVOICE_FONT_ROMAN,
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenant->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'enable',
        'status_bayar' => 'sudah_bayar',
        'customer_id' => 'CUST-BROWSER-001',
        'customer_name' => 'Pelanggan Browser Font',
        'username' => 'pelanggan-browser-font',
        'ppp_password' => 'secret123',
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-BROWSER-FONT-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenant->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Browser',
        'harga_dasar' => 150000,
        'total' => 150000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($tenant)
        ->get(route('invoices.print', $invoice))
        ->assertSuccessful()
        ->assertSee('font-family: "Times New Roman", Times, serif;', false)
        ->assertSee('Rp 150.000,00');

    $this->actingAs($tenant)
        ->get(route('invoices.nota', ['invoice' => $invoice, 'confirm' => 1]))
        ->assertSuccessful()
        ->assertSee('font-family: "Times New Roman", Times, serif;', false)
        ->assertSee('0,00')
        ->assertSee('150.000,00');

    $this->actingAs($tenant)
        ->get(route('invoices.nota-bulk', ['ids' => (string) $invoice->id]))
        ->assertSuccessful()
        ->assertSee('150.000,00')
        ->assertSee('0,00');
});

it('renders selected browser font in nota aktivasi view', function () {
    $tenant = makeInvoiceFontTenant();

    TenantSettings::getOrCreate($tenant->id)->update([
        'business_name' => 'ISP Font Browser',
        'browser_invoice_font' => TenantSettings::BROWSER_INVOICE_FONT_ROMAN,
    ]);

    $profile = PppProfile::query()->create([
        'owner_id' => $tenant->id,
        'name' => 'Paket Aktivasi Browser',
        'harga_modal' => 150000,
        'harga_promo' => 150000,
        'ppn' => 11,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
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
        'customer_id' => 'CUST-AKTIVASI-001',
        'customer_name' => 'Pelanggan Aktivasi Font',
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('4########'),
        'email' => 'pelanggan-aktivasi-font@example.test',
        'alamat' => 'Alamat Aktivasi Browser',
        'username' => 'pelanggan-aktivasi-font',
        'ppp_password' => 'secret123',
        'jatuh_tempo' => now()->addDays(7)->toDateString(),
    ]);

    $this->actingAs($tenant)
        ->get(route('ppp-users.nota-aktivasi', $pppUser))
        ->assertSuccessful()
        ->assertSee('font-family: "Times New Roman", Times, serif;', false)
        ->assertSee('Nota Aktivasi', false);
});

function makeInvoiceFontTenant(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}
