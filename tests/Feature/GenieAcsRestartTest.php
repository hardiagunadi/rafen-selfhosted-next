<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

function makeAdminUser(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

// ─── Restart GenieACS ────────────────────────────────────────────────────────

test('restart genieacs returns ok json when all services restart successfully', function () {
    Process::fake([
        'sudo systemctl restart genieacs-cwmp' => Process::result('', '', 0),
        'sudo systemctl restart genieacs-nbi' => Process::result('', '', 0),
        'sudo systemctl restart genieacs-fs' => Process::result('', '', 0),
    ]);

    $user = makeAdminUser();

    $response = $this->actingAs($user)
        ->postJson('/genieacs/restart');

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok'])
        ->assertJsonStructure(['status', 'message']);

    expect($response->json('message'))->toContain('berhasil');
});

test('restart genieacs returns error json when a service fails', function () {
    Process::fake([
        'sudo systemctl restart genieacs-cwmp' => Process::result('', '', 0),
        'sudo systemctl restart genieacs-nbi' => Process::result('', 'Failed to restart', 1),
        'sudo systemctl restart genieacs-fs' => Process::result('', '', 0),
    ]);

    $user = makeAdminUser();

    $response = $this->actingAs($user)
        ->postJson('/genieacs/restart');

    $response->assertStatus(500)
        ->assertJson(['status' => 'error']);
});

test('restart genieacs returns 403 for unauthenticated user', function () {
    $response = $this->postJson('/genieacs/restart');
    $response->assertStatus(401);
});

// ─── Log GenieACS routes ─────────────────────────────────────────────────────

test('genieacs log page accessible by admin', function () {
    $user = makeAdminUser();

    Http::fake([
        'localhost:7557/faults*' => Http::response([], 200),
        'localhost:7557/tasks*' => Http::response([], 200),
        'localhost:7557/devices*' => Http::response([], 200),
    ]);

    $response = $this->actingAs($user)->get('/logs/genieacs');
    $response->assertStatus(200);
});

test('genieacs status endpoint returns json with online key', function () {
    Http::fake([
        'localhost:7557/devices*' => Http::response([
            ['_id' => 'TEST-DEVICE-001', '_lastInform' => now()->toIso8601String()],
        ], 200),
        'localhost:7557/tasks*' => Http::response([], 200),
    ]);

    $user = makeAdminUser();
    $response = $this->actingAs($user)->getJson('/logs/genieacs/status');

    $response->assertStatus(200)
        ->assertJsonStructure(['online', 'nbi_url']);
});

test('genieacs delete task endpoint rejects unauthenticated', function () {
    $response = $this->deleteJson('/logs/genieacs/task');
    $response->assertStatus(401);
});

test('genieacs connection request endpoint rejects missing device_id', function () {
    $user = makeAdminUser();
    $response = $this->actingAs($user)->postJson('/logs/genieacs/connection-request', []);
    $response->assertStatus(422)
        ->assertJson(['status' => 'error']);
});

test('genieacs delete device rejects missing device_id', function () {
    $user = makeAdminUser();
    $response = $this->actingAs($user)->deleteJson('/logs/genieacs/device', []);
    $response->assertStatus(422)
        ->assertJson(['status' => 'error']);
});

test('genieacs delete device returns ok when device_id provided', function () {
    // Controller calls genieacsClient() which hits real GenieACS — mock at HTTP level
    Http::fake([
        'localhost:7557/tasks/*' => Http::response([], 200),
        'localhost:7557/devices/*' => Http::response('', 200),
    ]);

    $user = makeAdminUser();
    $response = $this->actingAs($user)->deleteJson('/logs/genieacs/device', [
        'device_id' => 'TEST-DEVICE-001',
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok']);
});
