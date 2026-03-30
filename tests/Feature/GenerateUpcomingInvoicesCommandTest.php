<?php

use App\Models\Invoice;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

function makePppProfile(User $owner, array $attributes = []): PppProfile
{
    return PppProfile::query()->create(array_merge([
        'owner_id' => $owner->id,
        'name' => 'Paket 20 Mbps',
        'harga_modal' => 150000,
        'harga_promo' => 125000,
        'ppn' => 11,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
    ], $attributes));
}

it('generates an upcoming invoice even when the user is still marked as paid', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-28 07:00:00'));

    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $profile = makePppProfile($tenantAdmin);

    $pppUser = PppUser::factory()->forOwner($tenantAdmin)->create([
        'ppp_profile_id' => $profile->id,
        'customer_id' => '000000010001',
        'customer_name' => 'Pelanggan Tanggal 10',
        'username' => 'pelanggan-tanggal-10',
        'status_bayar' => 'sudah_bayar',
        'jatuh_tempo' => '2026-04-10',
    ]);

    $this->artisan('invoice:generate-upcoming --days=14')
        ->assertExitCode(0);

    $invoice = Invoice::query()->where('ppp_user_id', $pppUser->id)->sole();

    expect($invoice->status)->toBe('unpaid')
        ->and($invoice->due_date?->toDateString())->toBe('2026-04-10')
        ->and($invoice->customer_name)->toBe('Pelanggan Tanggal 10');
});

it('keeps old unpaid invoices when generating the next billing period invoice', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-28 07:00:00'));

    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $profile = makePppProfile($tenantAdmin, [
        'name' => 'Paket Retain',
        'harga_modal' => 175000,
    ]);

    $pppUser = PppUser::factory()->forOwner($tenantAdmin)->create([
        'ppp_profile_id' => $profile->id,
        'customer_id' => '000000010002',
        'customer_name' => 'Pelanggan Tunggakan',
        'username' => 'pelanggan-tunggakan',
        'status_bayar' => 'belum_bayar',
        'jatuh_tempo' => '2026-04-10',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-OLD-2026030001',
        'ppp_user_id' => $pppUser->id,
        'ppp_profile_id' => $profile->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => $profile->name,
        'harga_dasar' => 175000,
        'total' => 175000,
        'due_date' => '2026-03-10',
        'status' => 'unpaid',
    ]);

    $this->artisan('invoice:generate-upcoming --days=14')
        ->assertExitCode(0);

    $dueDates = Invoice::query()
        ->where('ppp_user_id', $pppUser->id)
        ->orderBy('due_date')
        ->pluck('due_date')
        ->map(fn ($dueDate) => Carbon::parse($dueDate)->toDateString())
        ->all();

    expect($dueDates)->toBe(['2026-03-10', '2026-04-10']);
});
