<?php

use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTenantUserForProfileGroupDnsTest(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $overrides));
}

it('clears dns server when storing hotspot profile group', function () {
    $tenant = createTenantUserForProfileGroupDnsTest();

    $this->actingAs($tenant)
        ->post(route('profile-groups.store'), [
            'name' => 'Hotspot Group DNS',
            'owner' => $tenant->name,
            'type' => 'hotspot',
            'ip_pool_mode' => 'group_only',
            'ip_pool_name' => 'hs-dns-test',
            'dns_servers' => '8.8.8.8,1.1.1.1',
        ])
        ->assertRedirect(route('profile-groups.index'));

    $group = ProfileGroup::query()
        ->where('owner_id', $tenant->id)
        ->where('name', 'Hotspot Group DNS')
        ->first();

    expect($group)->not->toBeNull()
        ->and($group?->dns_servers)->toBeNull();
});

it('keeps dns server when storing pppoe profile group', function () {
    $tenant = createTenantUserForProfileGroupDnsTest();

    $this->actingAs($tenant)
        ->post(route('profile-groups.store'), [
            'name' => 'PPPoE Group DNS',
            'owner' => $tenant->name,
            'type' => 'pppoe',
            'ip_pool_mode' => 'group_only',
            'ip_pool_name' => 'pppoe-dns-test',
            'dns_servers' => '8.8.8.8,1.1.1.1',
        ])
        ->assertRedirect(route('profile-groups.index'));

    $group = ProfileGroup::query()
        ->where('owner_id', $tenant->id)
        ->where('name', 'PPPoE Group DNS')
        ->first();

    expect($group)->not->toBeNull()
        ->and($group?->dns_servers)->toBe('8.8.8.8,1.1.1.1');
});
