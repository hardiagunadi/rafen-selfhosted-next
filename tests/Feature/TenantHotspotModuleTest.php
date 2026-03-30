<?php

use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks hotspot routes when hotspot module is disabled', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'module_hotspot_enabled' => false,
    ]);

    $this->actingAs($tenant)
        ->get(route('hotspot-users.index'))
        ->assertNotFound();

    $this->actingAs($tenant)
        ->get(route('hotspot-profiles.index'))
        ->assertNotFound();

    $this->actingAs($tenant)
        ->get(route('sessions.hotspot'))
        ->assertNotFound();

    $this->actingAs($tenant)
        ->postJson(route('dashboard.api.hotspot-user.store'), [])
        ->assertNotFound()
        ->assertJsonPath('message', 'Modul tidak aktif untuk tenant ini.');
});

it('enforces hotspot module setting to teknisi under same tenant', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'module_hotspot_enabled' => false,
    ]);

    $this->actingAs($teknisi)
        ->get(route('hotspot-users.index'))
        ->assertNotFound();
});

it('allows tenant admin to update hotspot module toggle', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'module_hotspot_enabled' => true,
    ]);

    $this->actingAs($tenant)
        ->put(route('tenant-settings.update-modules'), [
            'module_hotspot_enabled' => false,
        ])
        ->assertRedirect();

    $settings = TenantSettings::getOrCreate($tenant->id)->fresh();

    expect($settings->module_hotspot_enabled)->toBeFalse();
});

it('prevents sub user from updating tenant modules', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $subUser = User::factory()->create([
        'parent_id' => $tenant->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($subUser)
        ->put(route('tenant-settings.update-modules'), [
            'module_hotspot_enabled' => true,
        ])
        ->assertForbidden();
});

it('shows simplified session menu labels for tenant', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'module_hotspot_enabled' => true,
    ]);

    $this->actingAs($tenant)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Session User')
        ->assertSee('PPPoE')
        ->assertSee('Hotspot')
        ->assertDontSee('PPPoE Aktif')
        ->assertDontSee('Hotspot Aktif');
});

it('hides hotspot session menu when hotspot module is disabled', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'module_hotspot_enabled' => false,
    ]);

    $this->actingAs($tenant)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Session User')
        ->assertSee('PPPoE')
        ->assertDontSee('Hotspot');
});
