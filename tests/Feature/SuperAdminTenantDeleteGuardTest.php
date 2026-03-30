<?php

use App\Models\HotspotUser;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSuperAdminTenantGuardTestUser(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

function createTenantForDeleteGuardTest(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'parent_id' => null,
    ]);
}

it('cascade deletes ppp customers when tenant is deleted', function () {
    $superAdmin = createSuperAdminTenantGuardTestUser();
    $tenant = createTenantForDeleteGuardTest();

    PppUser::query()->create([
        'owner_id' => $tenant->id,
        'status_akun' => 'enable',
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('super-admin.tenants.delete', $tenant))
        ->assertRedirect(route('super-admin.tenants'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $tenant->id]);
    $this->assertDatabaseMissing('ppp_users', ['owner_id' => $tenant->id]);
});

it('cascade deletes hotspot customers when tenant is deleted', function () {
    $superAdmin = createSuperAdminTenantGuardTestUser();
    $tenant = createTenantForDeleteGuardTest();

    HotspotUser::query()->create([
        'owner_id' => $tenant->id,
        'status_akun' => 'enable',
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('super-admin.tenants.delete', $tenant))
        ->assertRedirect(route('super-admin.tenants'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $tenant->id]);
    $this->assertDatabaseMissing('hotspot_users', ['owner_id' => $tenant->id]);
});

it('allows deleting tenant when there are no active customers', function () {
    $superAdmin = createSuperAdminTenantGuardTestUser();
    $tenant = createTenantForDeleteGuardTest();

    $this->actingAs($superAdmin)
        ->delete(route('super-admin.tenants.delete', $tenant))
        ->assertRedirect(route('super-admin.tenants'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $tenant->id]);
});
