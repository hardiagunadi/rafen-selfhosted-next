<?php

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeLogoTenant(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeLogoProfile(User $tenant): PppProfile
{
    return PppProfile::query()->create([
        'owner_id' => $tenant->id,
        'name' => 'Paket Logo Test',
        'harga_modal' => 150000,
        'harga_promo' => 150000,
        'ppn' => 11,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
    ]);
}

it('allows tenants to upload tenant and invoice logos separately', function () {
    Storage::fake('public');
    $this->withoutMiddleware();

    $tenant = makeLogoTenant();

    $this->actingAs($tenant)
        ->post(route('tenant-settings.upload-logo'), [
            'business_logo' => UploadedFile::fake()->image('tenant-logo.png', 320, 140),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Logo tenant berhasil diunggah.');

    $this->actingAs($tenant)
        ->post(route('tenant-settings.upload-invoice-logo'), [
            'invoice_logo' => UploadedFile::fake()->image('invoice-logo.png', 280, 280),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Logo nota berhasil diunggah.');

    $settings = TenantSettings::getOrCreate($tenant->id)->fresh();

    expect($settings->business_logo)->not->toBeNull()
        ->and($settings->invoice_logo)->not->toBeNull();

    Storage::disk('public')->assertExists($settings->business_logo);
    Storage::disk('public')->assertExists($settings->invoice_logo);
});

it('prefers the invoice logo in printed note views when available', function () {
    Storage::fake('public');

    $tenant = makeLogoTenant();
    $profile = makeLogoProfile($tenant);

    $businessLogo = UploadedFile::fake()->image('tenant-logo.png', 320, 140)
        ->store('business-logos', 'public');
    $invoiceLogo = UploadedFile::fake()->image('invoice-logo.png', 280, 280)
        ->store('invoice-logos', 'public');

    TenantSettings::getOrCreate($tenant->id)->update([
        'business_name' => 'Tenant Logo Test',
        'business_logo' => $businessLogo,
        'invoice_logo' => $invoiceLogo,
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
        'customer_id' => 'CUST-LOGO-001',
        'customer_name' => 'Pelanggan Logo',
        'nik' => fake()->numerify('################'),
        'nomor_hp' => '628'.fake()->unique()->numerify('4########'),
        'email' => 'pelanggan-logo@example.test',
        'alamat' => 'Alamat Logo',
        'username' => 'pelanggan-logo',
        'ppp_password' => 'secret123',
        'jatuh_tempo' => now()->addDays(7)->toDateString(),
    ]);

    $response = $this->actingAs($tenant)->get(route('ppp-users.nota-aktivasi', $pppUser));

    $response->assertOk()
        ->assertSee(Storage::url($invoiceLogo))
        ->assertDontSee(Storage::url($businessLogo));
});

it('uses lightweight object url preview logic for logo uploads', function () {
    $tenant = makeLogoTenant();

    $response = $this->actingAs($tenant)->get(route('tenant-settings.index'));

    $response->assertOk()
        ->assertSee('data-logo-browse="tenant"', false)
        ->assertSee('data-logo-form="tenant"', false)
        ->assertSee('accept=".jpg,.jpeg,.png,.gif,.webp"', false)
        ->assertSee('URL.createObjectURL(file)', false)
        ->assertSee('URL.revokeObjectURL(previousPreviewUrl)', false)
        ->assertSee('form.requestSubmit()', false)
        ->assertSee('Mengunggah...', false)
        ->assertDontSee('new FileReader()', false)
        ->assertDontSee('class="custom-file-input"', false)
        ->assertDontSee('<i class="fas fa-upload"></i> Upload', false);
});
