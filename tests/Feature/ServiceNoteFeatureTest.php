<?php

use App\Models\BankAccount;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ServiceNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function serviceNoteOwner(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $attributes));
}

function serviceNotePppUser(User $owner): PppUser
{
    $profile = PppProfile::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Paket Service Note',
    ]);

    return PppUser::query()->create([
        'status_registrasi' => 'aktif',
        'tipe_pembayaran' => 'prepaid',
        'status_bayar' => 'sudah_bayar',
        'status_akun' => 'enable',
        'owner_id' => $owner->id,
        'ppp_profile_id' => $profile->id,
        'tipe_service' => 'pppoe',
        'aksi_jatuh_tempo' => 'isolir',
        'tipe_ip' => 'dhcp',
        'customer_id' => 'CUST-SN-001',
        'customer_name' => 'Pelanggan Nota',
        'nomor_hp' => '6281234567890',
        'alamat' => 'Jl. Nota Layanan No. 1',
        'metode_login' => 'username_password',
        'username' => 'pelanggan-nota',
        'ppp_password' => 'secret123',
        'biaya_instalasi' => 150000,
    ]);
}

it('renders nota layanan presets for PPP users owned by the tenant', function () {
    $owner = serviceNoteOwner();
    $pppUser = serviceNotePppUser($owner);

    $this->actingAs($owner)
        ->get(route('ppp-users.nota-layanan', [
            'pppUser' => $pppUser,
            'type' => 'perbaikan',
        ]))
        ->assertSuccessful()
        ->assertSee('Nota Biaya Perbaikan')
        ->assertSee('NOTA BIAYA PERBAIKAN')
        ->assertSee('Pelanggan Nota');
});

it('forbids tenant from opening nota layanan for another tenant user', function () {
    $owner = serviceNoteOwner();
    $otherOwner = serviceNoteOwner();
    $pppUser = serviceNotePppUser($otherOwner);

    $this->actingAs($owner)
        ->get(route('ppp-users.nota-layanan', [
            'pppUser' => $pppUser,
            'type' => 'aktivasi',
        ]))
        ->assertForbidden();
});

it('stores service note and redirects to saved print page', function () {
    $owner = serviceNoteOwner();
    $pppUser = serviceNotePppUser($owner);

    $response = $this->actingAs($owner)->post(route('ppp-users.service-notes.store', $pppUser), [
        'note_type' => 'pemasangan',
        'document_title' => 'NOTA BIAYA PEMASANGAN',
        'summary_title' => 'RINCIAN PEMASANGAN',
        'document_number' => 'NTA-CUSTOM-001',
        'note_date' => now()->toDateString(),
        'service_type' => 'pppoe',
        'payment_method' => 'cash',
        'item_lines' => json_encode([
            ['label' => 'Biaya Pemasangan', 'amount' => 150000],
            ['label' => 'Material Kabel', 'amount' => 25000],
        ], JSON_THROW_ON_ERROR),
        'notes' => 'Instalasi baru selesai.',
        'footer' => 'Terima kasih.',
    ]);

    $serviceNote = ServiceNote::query()->firstOrFail();

    $response->assertRedirect(route('service-notes.print', $serviceNote));

    expect($serviceNote->owner_id)->toBe($owner->id)
        ->and($serviceNote->ppp_user_id)->toBe($pppUser->id)
        ->and((float) $serviceNote->total)->toEqual(175000.0)
        ->and((float) $serviceNote->cash_received)->toEqual(175000.0)
        ->and($serviceNote->document_title)->toBe('NOTA BIAYA PEMASANGAN');

    $this->actingAs($owner)
        ->get(route('service-notes.print', $serviceNote))
        ->assertSuccessful()
        ->assertSee('NTA-CUSTOM-001')
        ->assertSee('Biaya Pemasangan')
        ->assertSee('175.000')
        ->assertSee('Pendapatan tersimpan dengan nomor');
});

it('stores transfer destination snapshot and shows it on printed note', function () {
    $owner = serviceNoteOwner();
    $pppUser = serviceNotePppUser($owner);

    BankAccount::query()->create([
        'user_id' => $owner->id,
        'bank_name' => 'BRI',
        'bank_code' => '002',
        'account_number' => '9876543210',
        'account_name' => 'PT Rafen Tenant',
        'branch' => 'Semarang',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->actingAs($owner)->post(route('ppp-users.service-notes.store', $pppUser), [
        'note_type' => 'perbaikan',
        'document_title' => 'NOTA BIAYA PERBAIKAN',
        'summary_title' => 'RINCIAN PERBAIKAN',
        'document_number' => 'NTA-TRANSFER-001',
        'note_date' => now()->toDateString(),
        'service_type' => 'pppoe',
        'payment_method' => 'transfer',
        'item_lines' => json_encode([
            ['label' => 'Biaya Perbaikan', 'amount' => 90000],
        ], JSON_THROW_ON_ERROR),
        'notes' => 'Dibayar via transfer.',
        'footer' => 'Mohon simpan bukti transfer.',
    ])->assertSessionHasNoErrors();

    $serviceNote = ServiceNote::query()->firstOrFail();

    expect($serviceNote->payment_method)->toBe('transfer')
        ->and($serviceNote->transfer_accounts)->toBe([
            [
                'bank_name' => 'BRI',
                'account_number' => '9876543210',
                'account_name' => 'PT Rafen Tenant',
                'branch' => 'Semarang',
            ],
        ]);

    BankAccount::query()->where('user_id', $owner->id)->update([
        'bank_name' => 'Mandiri',
        'account_number' => '000111222333',
        'account_name' => 'Perubahan Baru',
        'branch' => 'Jakarta',
    ]);

    $this->actingAs($owner)
        ->get(route('service-notes.print', $serviceNote))
        ->assertSuccessful()
        ->assertSee('DAFTAR REKENING PEMBAYARAN TRANSFER')
        ->assertSee('BRI')
        ->assertSee('9876543210')
        ->assertDontSee('Mandiri');
});

it('shows service note history page with owner-scoped records', function () {
    $owner = serviceNoteOwner();
    $otherOwner = serviceNoteOwner();
    $pppUser = serviceNotePppUser($owner);
    $otherPppUser = serviceNotePppUser($otherOwner);

    ServiceNote::factory()->forOwner($owner)->forPppUser($pppUser)->create([
        'document_number' => 'NTA-HISTORY-001',
        'customer_name' => 'Pelanggan History',
    ]);

    ServiceNote::factory()->forOwner($otherOwner)->forPppUser($otherPppUser)->create([
        'document_number' => 'NTA-HISTORY-OTHER',
        'customer_name' => 'Pelanggan Lain',
    ]);

    $this->actingAs($owner)
        ->get(route('service-notes.index'))
        ->assertSuccessful()
        ->assertSee('NTA-HISTORY-001')
        ->assertDontSee('NTA-HISTORY-OTHER');
});

it('includes service note totals in customer income report', function () {
    $owner = serviceNoteOwner();
    $pppUser = serviceNotePppUser($owner);

    ServiceNote::factory()
        ->forOwner($owner)
        ->forPppUser($pppUser)
        ->create([
            'document_number' => 'NTA-RPT-001',
            'total' => 210000,
            'subtotal' => 210000,
            'paid_at' => now(),
            'note_date' => now()->toDateString(),
            'item_lines' => [
                ['label' => 'Biaya Perbaikan', 'amount' => 210000],
            ],
        ]);

    $this->actingAs($owner)
        ->get(route('reports.income', [
            'report' => 'daily',
            'date' => now()->toDateString(),
            'tipe_user' => 'customer',
        ]))
        ->assertSuccessful()
        ->assertViewHas('report', function (array $report): bool {
            return (float) ($report['summary']['total_income'] ?? 0) === 210000.0
                && (float) ($report['summary']['customer_income'] ?? 0) === 210000.0
                && $report['items']->count() === 1
                && ($report['items']->first()['reference'] ?? null) === 'NTA-RPT-001';
        });
});
