<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createActiveUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $overrides));
}

it('shows new help center cards on index page', function () {
    $user = createActiveUser();

    $this->actingAs($user)
        ->get(route('help.index'))
        ->assertSuccessful()
        ->assertSeeText('Panduan Per Role', false)
        ->assertSeeText('Peta Fitur Operasional', false)
        ->assertSeeText('FAQ Operasional', false)
        ->assertSeeText('WhatsApp Gateway', false)
        ->assertSeeText('CPE & GenieACS', false)
        ->assertSeeText('Chat WA & Tiket', false)
        ->assertSeeText('Operasional Super Admin', false);
});

it('shows role summary based on current user role', function () {
    $user = createActiveUser([
        'role' => 'teknisi',
    ]);

    $this->actingAs($user)
        ->get(route('help.index'))
        ->assertSuccessful()
        ->assertSeeText('Ringkasan akses untuk role Anda: TEKNISI', false)
        ->assertSeeText('Monitoring OLT (Polling Sekarang)', false);
});

it('opens each new help topic page', function (string $slug, string $expectedHeading) {
    $user = createActiveUser();

    $this->actingAs($user)
        ->get(route('help.topic', $slug))
        ->assertSuccessful()
        ->assertSeeText($expectedHeading, false);
})->with([
    ['panduan-role', 'Panduan Per Role'],
    ['fitur-operasional', 'Peta Fitur Operasional RAFEN'],
    ['faq', 'FAQ Operasional RAFEN'],
    ['whatsapp-gateway', 'WhatsApp Gateway — Panduan Praktis'],
    ['pelanggan-infrastruktur', 'Pelanggan, Peta, dan ODP'],
    ['cpe-genieacs', 'CPE Management & GenieACS'],
    ['chat-wa-ticketing', 'Chat WA & Tiket Pengaduan'],
    ['gangguan-jaringan', 'Gangguan Jaringan'],
    ['jadwal-shift', 'Jadwal Shift'],
    ['wallet-withdrawal', 'Wallet & Withdrawal Tenant'],
    ['tool-sistem-audit', 'Tool Sistem & Audit Log'],
    ['super-admin-platform', 'Operasional Super Admin Platform'],
]);

it('returns not found for unknown help topic slug', function () {
    $user = createActiveUser();

    $this->actingAs($user)
        ->get(route('help.topic', 'topik-tidak-ada'))
        ->assertNotFound();
});
