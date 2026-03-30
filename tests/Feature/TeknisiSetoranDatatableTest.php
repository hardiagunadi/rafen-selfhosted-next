<?php

use App\Models\TeknisiSetoran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns aggregated totals for teknisi setoran datatable', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $verifier = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'keuangan',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TeknisiSetoran::query()->create([
        'owner_id' => $tenantAdmin->id,
        'teknisi_id' => $teknisi->id,
        'period_date' => now()->toDateString(),
        'total_invoices' => 2,
        'total_tagihan' => 175000,
        'total_cash' => 125000,
        'status' => 'draft',
    ]);

    TeknisiSetoran::query()->create([
        'owner_id' => $tenantAdmin->id,
        'teknisi_id' => $teknisi->id,
        'verified_by' => $verifier->id,
        'period_date' => now()->subDay()->toDateString(),
        'total_invoices' => 1,
        'total_tagihan' => 50000,
        'total_cash' => 50000,
        'status' => 'verified',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('teknisi-setoran.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('summary.total_tagihan', 225000)
        ->assertJsonPath('summary.total_cash', 175000)
        ->assertJsonPath('summary.total_tagihan_formatted', '225.000')
        ->assertJsonPath('summary.total_cash_formatted', '175.000');
});

it('returns filtered aggregated totals for teknisi setoran datatable', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TeknisiSetoran::query()->create([
        'owner_id' => $tenantAdmin->id,
        'teknisi_id' => $teknisi->id,
        'period_date' => now()->toDateString(),
        'total_invoices' => 2,
        'total_tagihan' => 100000,
        'total_cash' => 80000,
        'status' => 'draft',
    ]);

    TeknisiSetoran::query()->create([
        'owner_id' => $tenantAdmin->id,
        'teknisi_id' => $teknisi->id,
        'period_date' => now()->subDays(2)->toDateString(),
        'total_invoices' => 3,
        'total_tagihan' => 210000,
        'total_cash' => 180000,
        'status' => 'verified',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('teknisi-setoran.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
            'status' => 'verified',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('summary.total_tagihan', 210000)
        ->assertJsonPath('summary.total_cash', 180000)
        ->assertJsonPath('summary.total_tagihan_formatted', '210.000')
        ->assertJsonPath('summary.total_cash_formatted', '180.000');
});
