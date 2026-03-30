<?php

use App\Models\HotspotProfile;
use App\Models\MikrotikConnection;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createActiveTenant(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $overrides));
}

it('exports shared users for hotspot profile group', function () {
    $tenant = createActiveTenant();

    $connection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'Router A',
        'is_active' => true,
    ]);

    $group = ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'mikrotik_connection_id' => $connection->id,
        'name' => 'hotspot-group-a',
        'type' => 'hotspot',
        'ip_pool_mode' => 'group_only',
        'ip_pool_name' => 'hs-pool-a',
        'parent_queue' => null,
    ]);

    HotspotProfile::factory()->create([
        'owner_id' => $tenant->id,
        'profile_group_id' => $group->id,
        'shared_users' => 4,
    ]);

    $clientMock = \Mockery::mock('overload:App\Services\MikrotikApiClient');
    $clientMock->shouldReceive('connect')->once();
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/print', [], ['name' => 'hotspot-group-a'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/add', \Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'hotspot-group-a'
                && ($payload['address-pool'] ?? null) === 'hs-pool-a'
                && ($payload['shared-users'] ?? null) === '4'
                && ! array_key_exists('dns-server', $payload);
        }))
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('disconnect')->once();

    $this->actingAs($tenant)
        ->post(route('profile-groups.export', $group))
        ->assertRedirect(route('profile-groups.index'))
        ->assertSessionHas('status');
});

it('falls back to shared users 1 when hotspot profile is not linked', function () {
    $tenant = createActiveTenant();

    $connection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'Router B',
        'is_active' => true,
    ]);

    $group = ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'mikrotik_connection_id' => $connection->id,
        'name' => 'hotspot-group-b',
        'type' => 'hotspot',
        'ip_pool_mode' => 'group_only',
        'ip_pool_name' => 'hs-pool-b',
        'parent_queue' => null,
    ]);

    $clientMock = \Mockery::mock('overload:App\Services\MikrotikApiClient');
    $clientMock->shouldReceive('connect')->once();
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/print', [], ['name' => 'hotspot-group-b'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/add', \Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'hotspot-group-b'
                && ($payload['address-pool'] ?? null) === 'hs-pool-b'
                && ($payload['shared-users'] ?? null) === '1'
                && ! array_key_exists('dns-server', $payload);
        }))
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('disconnect')->once();

    $this->actingAs($tenant)
        ->post(route('profile-groups.export', $group))
        ->assertRedirect(route('profile-groups.index'))
        ->assertSessionHas('status');
});

it('creates parent queue from database when missing on target router', function () {
    $tenant = createActiveTenant();

    $connection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'Router C',
        'is_active' => true,
    ]);

    $group = ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'mikrotik_connection_id' => $connection->id,
        'name' => 'hotspot-group-c',
        'type' => 'hotspot',
        'ip_pool_mode' => 'group_only',
        'ip_pool_name' => 'hs-pool-c',
        'parent_queue' => 'queue-does-not-exist',
        'ip_address' => '10.70.0.0',
        'netmask' => '24',
    ]);

    HotspotProfile::factory()->create([
        'owner_id' => $tenant->id,
        'profile_group_id' => $group->id,
        'shared_users' => 3,
    ]);

    $clientMock = \Mockery::mock('overload:App\Services\MikrotikApiClient');
    $clientMock->shouldReceive('connect')->once();
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/queue/simple/print', [], ['name' => 'queue-does-not-exist'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/queue/tree/print', [], ['name' => 'queue-does-not-exist'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/queue/simple/add', \Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'queue-does-not-exist'
                && ($payload['target'] ?? null) === '10.70.0.0/24'
                && ($payload['max-limit'] ?? null) === '0/0';
        }))
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/print', [], ['name' => 'hotspot-group-c'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/add', \Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'hotspot-group-c'
                && ($payload['address-pool'] ?? null) === 'hs-pool-c'
                && ($payload['shared-users'] ?? null) === '3'
                && ($payload['parent-queue'] ?? null) === 'queue-does-not-exist';
        }))
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('disconnect')->once();

    $this->actingAs($tenant)
        ->post(route('profile-groups.export', $group))
        ->assertRedirect(route('profile-groups.index'))
        ->assertSessionHas('status');
});

it('skips parent queue when queue cannot be created on target router', function () {
    $tenant = createActiveTenant();

    $connection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'Router D',
        'is_active' => true,
    ]);

    $group = ProfileGroup::factory()->create([
        'owner_id' => $tenant->id,
        'mikrotik_connection_id' => $connection->id,
        'name' => 'hotspot-group-d',
        'type' => 'hotspot',
        'ip_pool_mode' => 'group_only',
        'ip_pool_name' => 'hs-pool-d',
        'parent_queue' => 'queue-add-fails',
        'ip_address' => '10.80.0.0',
        'netmask' => '24',
    ]);

    HotspotProfile::factory()->create([
        'owner_id' => $tenant->id,
        'profile_group_id' => $group->id,
        'shared_users' => 2,
    ]);

    $clientMock = \Mockery::mock('overload:App\Services\MikrotikApiClient');
    $clientMock->shouldReceive('connect')->once();
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/queue/simple/print', [], ['name' => 'queue-add-fails'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/queue/tree/print', [], ['name' => 'queue-add-fails'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/queue/simple/add', \Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'queue-add-fails'
                && ($payload['target'] ?? null) === '10.80.0.0/24';
        }))
        ->andThrow(new \RuntimeException('queue add failed'));
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/print', [], ['name' => 'hotspot-group-d'])
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/ip/hotspot/user/profile/add', \Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'hotspot-group-d'
                && ($payload['address-pool'] ?? null) === 'hs-pool-d'
                && ($payload['shared-users'] ?? null) === '2'
                && ! array_key_exists('parent-queue', $payload);
        }))
        ->andReturn(['data' => [], 'done' => []]);
    $clientMock->shouldReceive('disconnect')->once();

    $this->actingAs($tenant)
        ->post(route('profile-groups.export', $group))
        ->assertRedirect(route('profile-groups.index'))
        ->assertSessionHas('status');
});
