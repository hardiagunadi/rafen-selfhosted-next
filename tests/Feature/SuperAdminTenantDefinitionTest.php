<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only administrator owners in super admin tenant list', function () {
    $superAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);

    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'parent_id' => null,
    ]);

    $nonTenantOwner = User::factory()->create([
        'role' => 'noc',
        'is_super_admin' => false,
        'parent_id' => null,
    ]);

    User::factory()->create([
        'role' => 'teknisi',
        'is_super_admin' => false,
        'parent_id' => $tenantAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.tenants'))
        ->assertSuccessful()
        ->assertSee($tenantAdmin->email)
        ->assertDontSee($nonTenantOwner->email);
});

it('returns not found when super admin opens tenant detail for non administrator role', function () {
    $superAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);

    $nonTenantOwner = User::factory()->create([
        'role' => 'noc',
        'is_super_admin' => false,
        'parent_id' => null,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.tenants.show', $nonTenantOwner))
        ->assertNotFound();
});
