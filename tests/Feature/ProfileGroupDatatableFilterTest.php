<?php

use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTenantUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $overrides));
}

it('filters profile group datatable by pppoe type', function () {
    $tenant = createTenantUser();
    $otherTenant = createTenantUser();

    ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'type' => 'pppoe',
        'name' => 'PPPoE Group A',
    ]);

    ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'type' => 'hotspot',
        'name' => 'Hotspot Group A',
    ]);

    ProfileGroup::factory()->create([
        'owner_id' => $otherTenant->id,
        'type' => 'pppoe',
        'name' => 'PPPoE Tenant Lain',
    ]);

    $response = $this->actingAs($tenant)
        ->getJson(route('profile-groups.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_type' => 'pppoe',
        ]));

    $response->assertSuccessful()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1);

    $types = collect($response->json('data'))->pluck('type')->unique()->values()->all();
    $names = collect($response->json('data'))->pluck('name')->values()->all();

    expect($types)->toBe(['PPPOE'])
        ->and($names)->toBe(['PPPoE Group A']);
});

it('filters profile group datatable by hotspot type', function () {
    $tenant = createTenantUser();

    ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'type' => 'pppoe',
        'name' => 'PPPoE Group B',
    ]);

    ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'type' => 'hotspot',
        'name' => 'Hotspot Group B',
    ]);

    $response = $this->actingAs($tenant)
        ->getJson(route('profile-groups.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_type' => 'hotspot',
        ]));

    $response->assertSuccessful()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1);

    $types = collect($response->json('data'))->pluck('type')->unique()->values()->all();
    $names = collect($response->json('data'))->pluck('name')->values()->all();

    expect($types)->toBe(['HOTSPOT'])
        ->and($names)->toBe(['Hotspot Group B']);
});

it('normalizes group type filter input before applying query', function () {
    $tenant = createTenantUser();

    ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'type' => 'pppoe',
        'name' => 'PPPoE Group C',
    ]);

    ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'type' => 'hotspot',
        'name' => 'Hotspot Group C',
    ]);

    $response = $this->actingAs($tenant)
        ->getJson(route('profile-groups.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_type' => '  PPPOE ',
        ]));

    $response->assertSuccessful()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1);

    $types = collect($response->json('data'))->pluck('type')->unique()->values()->all();
    $names = collect($response->json('data'))->pluck('name')->values()->all();

    expect($types)->toBe(['PPPOE'])
        ->and($names)->toBe(['PPPoE Group C']);
});
