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

function makeAddInvoiceTenant(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeAddInvoiceProfile(User $owner, array $attributes = []): PppProfile
{
    return PppProfile::query()->create(array_merge([
        'owner_id' => $owner->id,
        'name' => 'Paket Manual Invoice',
        'harga_modal' => 220000,
        'harga_promo' => 220000,
        'ppn' => 0,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
    ], $attributes));
}

it('creates the next billing invoice when the current due date is already occupied by a paid invoice', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-22 10:00:00'));

    $tenant = makeAddInvoiceTenant();
    $profile = makeAddInvoiceProfile($tenant);

    $pppUser = PppUser::factory()->forOwner($tenant)->create([
        'ppp_profile_id' => $profile->id,
        'customer_id' => '000000020001',
        'customer_name' => 'Pelanggan Manual Invoice',
        'username' => 'manual-invoice-user',
        'status_bayar' => 'sudah_bayar',
        'jatuh_tempo' => '2026-04-22',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-PAID-2026040001',
        'ppp_user_id' => $pppUser->id,
        'ppp_profile_id' => $profile->id,
        'owner_id' => $tenant->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => $profile->name,
        'harga_dasar' => 220000,
        'harga_asli' => 220000,
        'ppn_percent' => 0,
        'ppn_amount' => 0,
        'total' => 220000,
        'due_date' => '2026-04-22',
        'status' => 'paid',
        'paid_at' => '2026-03-22 14:38:56',
        'payment_token' => Invoice::generatePaymentToken(),
    ]);

    $response = $this->actingAs($tenant)
        ->postJson(route('ppp-users.add-invoice', $pppUser));

    $response->assertOk()
        ->assertJson([
            'status' => 'Tagihan berhasil ditambahkan.',
            'due_date' => '2026-05-22',
        ]);

    $dueDates = Invoice::query()
        ->where('ppp_user_id', $pppUser->id)
        ->orderBy('due_date')
        ->pluck('due_date')
        ->map(fn ($dueDate) => Carbon::parse($dueDate)->toDateString())
        ->all();

    expect($dueDates)->toBe(['2026-04-22', '2026-05-22'])
        ->and(
            Invoice::query()
                ->where('ppp_user_id', $pppUser->id)
                ->whereDate('due_date', '2026-05-22')
                ->value('status')
        )->toBe('unpaid');
});

it('returns a clear validation error when a manual invoice cannot be created without an active profile', function () {
    $tenant = makeAddInvoiceTenant();

    $pppUser = PppUser::factory()->forOwner($tenant)->create([
        'ppp_profile_id' => null,
        'status_bayar' => 'sudah_bayar',
        'jatuh_tempo' => '2026-04-22',
    ]);

    $response = $this->actingAs($tenant)
        ->postJson(route('ppp-users.add-invoice', $pppUser));

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Tagihan tidak dapat ditambahkan karena pelanggan belum memiliki profil paket aktif.',
        ]);

    expect(Invoice::query()->where('ppp_user_id', $pppUser->id)->count())->toBe(0);
});
