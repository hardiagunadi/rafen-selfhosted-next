<?php

use App\Models\MikrotikConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders api dashboard when selecting an accessible router by connection id', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $firstConnection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'Router Alpha',
        'is_active' => true,
    ]);

    $selectedConnection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
        'name' => 'Router Beta',
        'is_active' => true,
    ]);

    $clientMock = Mockery::mock('overload:App\Services\MikrotikApiClient');
    $clientMock->shouldReceive('command')
        ->once()
        ->with('/system/resource/print')
        ->andReturn([
            'data' => [[
                'platform' => 'MikroTik',
                'version' => '7.15.1 (stable)',
                'cpu' => 'ARM',
                'cpu-count' => '4',
                'cpu-frequency' => '1400',
                'cpu-load' => '12',
                'total-memory' => '100',
                'free-memory' => '25',
                'total-hdd-space' => '200',
                'free-hdd-space' => '80',
                'build-time' => '2025-02-06 09:10:24',
                'uptime' => '1d2h',
            ]],
            'done' => [],
        ]);
    $clientMock->shouldReceive('disconnect')->once();

    $this->actingAs($tenant)
        ->get(route('dashboard.api', ['connection_id' => $selectedConnection->id]))
        ->assertSuccessful()
        ->assertViewHas('selectedConnection', function (?MikrotikConnection $connection) use ($selectedConnection): bool {
            return $connection?->is($selectedConnection) === true;
        })
        ->assertViewHas('connections', function (Collection $connections) use ($firstConnection, $selectedConnection): bool {
            return $connections->modelKeys() === [$firstConnection->id, $selectedConnection->id];
        })
        ->assertSee('Router Beta');
});
