<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows data keuangan menu for administrator and keuangan', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $keuangan = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'keuangan',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Data Keuangan');

    $this->actingAs($keuangan)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Data Keuangan');
});

it('does not show informasi layanan card on the dashboard', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $keuangan = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'keuangan',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($keuangan)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSeeText('Informasi Layanan');

    $this->actingAs($tenantAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSeeText('Informasi Layanan');

    $this->actingAs($teknisi)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSeeText('Informasi Layanan');
});

it('shows data keuangan menu for teknisi and hides it for unrelated roles', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $noc = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'noc',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($noc)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSeeText('Data Keuangan');

    $this->actingAs($teknisi)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Data Keuangan')
        ->assertDontSeeText('Profil Paket');
});

it('allows administrator and keuangan roles to access income report', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $keuangan = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'keuangan',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('reports.income'))
        ->assertSuccessful();

    $this->actingAs($keuangan)
        ->get(route('reports.income'))
        ->assertSuccessful();
});

it('forbids noc role and allows teknisi role to access income report', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $noc = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'noc',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($noc)
        ->get(route('reports.income'))
        ->assertForbidden();

    $this->actingAs($teknisi)
        ->get(route('reports.income'))
        ->assertSuccessful();
});
