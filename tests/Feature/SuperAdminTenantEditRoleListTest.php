<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows tenant role list on super admin tenant detail page', function () {
    $superAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);

    $tenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'parent_id' => null,
    ]);

    User::factory()->create([
        'role' => 'noc',
        'parent_id' => $tenant->id,
    ]);

    User::factory()->create([
        'role' => 'keuangan',
        'parent_id' => $tenant->id,
    ]);

    User::factory()->create([
        'role' => 'teknisi',
        'parent_id' => $tenant->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.tenants.show', $tenant))
        ->assertSuccessful()
        ->assertSee('Daftar Role Tenant')
        ->assertSee('Administrator')
        ->assertSee('NOC')
        ->assertSee('Keuangan')
        ->assertSee('Teknisi');
});

it('uses license wording in quick actions for license tenants', function () {
    $superAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);

    $licenseTenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'parent_id' => null,
        'subscription_method' => 'license',
        'subscription_status' => 'active',
        'trial_days_remaining' => 0,
        'subscription_expires_at' => now()->addYear()->toDateString(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.tenants.show', $licenseTenant))
        ->assertSuccessful()
        ->assertSee('Perpanjang Lisensi')
        ->assertSee('Perpanjang Lisensi Tahunan')
        ->assertDontSee('Perpanjang Langganan');
});
