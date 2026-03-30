<?php

use App\Models\CpeDevice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function cpeTenantAdmin(): User
{
    $user = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($user->id)->update([
        'genieacs_url' => 'http://genieacs.test:7557',
    ]);

    return $user;
}

function cpePppUser(User $owner): PppUser
{
    return PppUser::factory()->forOwner($owner)->create([
        'username' => 'testuser@isp.id',
    ]);
}

// ---------------------------------------------------------------------------
// CPE Index
// ---------------------------------------------------------------------------

it('admin can access cpe index', function () {
    $admin = cpeTenantAdmin();

    $this->actingAs($admin)
        ->get(route('cpe.index'))
        ->assertSuccessful();
});

it('unauthenticated user cannot access cpe index', function () {
    $this->get(route('cpe.index'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// CPE Show (AJAX info)
// ---------------------------------------------------------------------------

it('returns not linked when no cpe device exists', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);

    $this->actingAs($admin)
        ->getJson(route('cpe.show', $pppUser->id))
        ->assertOk()
        ->assertJson(['linked' => false]);
});

it('returns linked true when cpe device exists', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'manufacturer' => 'CMDC',
        'model' => 'H3-2S XPON',
        'status' => 'online',
    ]);

    $this->actingAs($admin)
        ->getJson(route('cpe.show', $pppUser->id))
        ->assertOk()
        ->assertJson(['linked' => true])
        ->assertJsonPath('device.manufacturer', 'CMDC');
});

// ---------------------------------------------------------------------------
// Sync
// ---------------------------------------------------------------------------

it('sync finds device and creates cpe record', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);

    $fakeDevice = [
        '_id' => 'A861DF-H3-2S-CMDCA106B700',
        'InternetGatewayDevice' => [
            'DeviceInfo' => [
                'Manufacturer' => ['_value' => 'CMDC'],
                'ModelName' => ['_value' => 'H3-2S XPON'],
                'SoftwareVersion' => ['_value' => 'V1.1.20P1T4'],
                'SerialNumber' => ['_value' => 'CMDCA106B700'],
                'UpTime' => ['_value' => '3600'],
            ],
        ],
        '_lastInform' => now()->toIso8601String(),
    ];

    // Fake HTTP calls to GenieACS NBI
    Http::fake([
        '*/devices/*' => Http::sequence()
            ->push([$fakeDevice], 200)  // findDeviceByUsername (search by igd path)
            ->push([], 202)             // refreshObject task
            ->pushStatus(200),          // fallback
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.sync', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('cpe_devices', [
        'ppp_user_id' => $pppUser->id,
        'genieacs_device_id' => 'A861DF-H3-2S-CMDCA106B700',
    ]);
});

it('sync returns 404 when device not found in genieacs', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);

    // Both IGD and Device paths return empty — device not found
    Http::fake([
        '*/devices/*' => Http::response([], 200),
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.sync', $pppUser->id))
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

// ---------------------------------------------------------------------------
// Reboot
// ---------------------------------------------------------------------------

it('admin can reboot a linked cpe device', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => 'abc123'], 202)]);

    $this->actingAs($admin)
        ->postJson(route('cpe.reboot', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('queued', true);
});

it('teknisi can reboot a linked cpe device', function () {
    $admin = cpeTenantAdmin();
    $teknisi = User::factory()->create([
        'role' => 'teknisi',
        'parent_id' => $admin->id,
    ]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => null], 200)]);

    $this->actingAs($teknisi)
        ->postJson(route('cpe.reboot', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('cs role cannot reboot cpe device', function () {
    $admin = cpeTenantAdmin();
    $cs = User::factory()->create(['role' => 'cs', 'parent_id' => $admin->id]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($cs)
        ->postJson(route('cpe.reboot', $pppUser->id))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update WiFi
// ---------------------------------------------------------------------------

it('admin can update wifi settings', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'param_profile' => 'igd',
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => 'xyz'], 202)]);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid' => 'MyWifi',
            'password' => 'password123',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('admin can update wifi ssid without changing password', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-SSID-ONLY-001',
        'param_profile' => 'igd',
    ]);

    Http::fake(function ($request) {
        if ($request->method() === 'POST' && str_contains($request->url(), '/devices/TEST-DEVICE-SSID-ONLY-001/tasks?')) {
            expect($request['parameterValues'])->toHaveCount(1)
                ->and($request['parameterValues'][0][0])->toBe(config('genieacs.params.igd.wifi_ssid'))
                ->and($request['parameterValues'][0][1])->toBe('MyWifiOnly');

            return Http::response(['_id' => 'xyz'], 202);
        }

        if ($request->method() === 'DELETE' && str_contains($request->url(), '/tasks/xyz')) {
            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid' => 'MyWifiOnly',
            'password' => '',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('teknisi, noc, and it_support can update wifi ssid without changing password', function (string $role) {
    $admin = cpeTenantAdmin();
    $staff = User::factory()->create([
        'role' => $role,
        'parent_id' => $admin->id,
    ]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-ROLE-'.$role,
        'param_profile' => 'igd',
    ]);

    Http::fake(function ($request) {
        if ($request->method() === 'POST' && str_contains($request->url(), '/tasks?connection_request&timeout=3000')) {
            return Http::response(['_id' => 'wifi-role-task'], 202);
        }

        if ($request->method() === 'DELETE' && str_contains($request->url(), '/tasks/wifi-role-task')) {
            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    $this->actingAs($staff)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid' => 'Wifi-'.$role,
            'password' => '',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
})->with([
    'teknisi' => 'teknisi',
    'noc' => 'noc',
    'it_support' => 'it_support',
]);

it('cs role cannot update wifi settings', function () {
    $admin = cpeTenantAdmin();
    $cs = User::factory()->create([
        'role' => 'cs',
        'parent_id' => $admin->id,
    ]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-CS-WIFI-001',
        'param_profile' => 'igd',
    ]);

    $this->actingAs($cs)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid' => 'Wifi-CS',
            'password' => '',
        ])
        ->assertForbidden();
});

it('wifi update validates ssid max 32 chars', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid' => str_repeat('A', 33),
            'password' => 'password123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ssid']);
});

it('wifi update validates password min 8 chars', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-wifi', $pppUser->id), [
            'ssid' => 'ValidSSID',
            'password' => 'short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

// ---------------------------------------------------------------------------
// Update PPPoE
// ---------------------------------------------------------------------------

it('admin can update pppoe credentials', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'param_profile' => 'igd',
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => null], 200)]);

    $this->actingAs($admin)
        ->postJson(route('cpe.update-pppoe', $pppUser->id), [
            'username' => 'user@isp.id',
            'password' => 'pppoepass',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

// ---------------------------------------------------------------------------
// Tenant Isolation
// ---------------------------------------------------------------------------

it('tenant admin cannot access cpe of another tenant', function () {
    $admin1 = cpeTenantAdmin();
    $admin2 = cpeTenantAdmin();
    $pppUser = cpePppUser($admin1);

    $this->actingAs($admin2)
        ->getJson(route('cpe.show', $pppUser->id))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

it('admin can unlink cpe device', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    $device = CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('cpe.destroy', $pppUser->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('cpe_devices', ['id' => $device->id]);
});

// ---------------------------------------------------------------------------
// Update WiFi by Index (multi-SSID) — channel
// ---------------------------------------------------------------------------

it('admin can update wifi by index with channel', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'cached_params' => [
            'wifi_networks' => [
                ['index' => 1, 'ssid' => 'OldSSID', 'password' => 'oldpass1', 'enabled' => true, 'channel' => 6, 'band' => '2.4GHz'],
            ],
        ],
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => 'abc'], 202)]);

    $this->actingAs($admin)
        ->postJson(route('cpe.wifi-by-index', [$pppUser->id, 1]), [
            'ssid' => 'NewSSID',
            'channel' => 11,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $device = CpeDevice::where('ppp_user_id', $pppUser->id)->first();
    expect($device->cached_params['wifi_networks'][0]['channel'])->toBe(11);
    expect($device->cached_params['wifi_networks'][0]['ssid'])->toBe('NewSSID');
});

it('wifi by index validates channel max 165', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.wifi-by-index', [$pppUser->id, 1]), [
            'ssid' => 'ValidSSID',
            'channel' => 200,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['channel']);
});

it('teknisi cannot change channel via wifi by index', function () {
    $admin = cpeTenantAdmin();
    $teknisi = User::factory()->create(['role' => 'teknisi', 'parent_id' => $admin->id]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => 'abc'], 202)]);

    $this->actingAs($teknisi)
        ->postJson(route('cpe.wifi-by-index', [$pppUser->id, 1]), [
            'ssid' => 'SomeSSID',
            'channel' => 6,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['channel']);
});

it('wifi by index returns 422 when no params sent', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->postJson(route('cpe.wifi-by-index', [$pppUser->id, 1]), [])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

// ---------------------------------------------------------------------------
// Update WAN Connection
// ---------------------------------------------------------------------------

it('admin can update wan connection', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'cached_params' => [
            'wan_connections' => [
                [
                    'key' => '1.1.1',
                    'enabled' => true,
                    'connection_type' => 'IP_Routed',
                    'vlan_id' => 100,
                    'vlan_prio' => 0,
                    'dns_servers' => '8.8.8.8',
                    'lan_interface' => '',
                    'ppp_idx' => null,
                ],
            ],
        ],
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => 'task1'], 200)]);

    $this->actingAs($admin)
        ->putJson(route('cpe.wan-update', [$pppUser->id, 1, 1, 1]), [
            'vlan_id' => 200,
            'dns_servers' => '1.1.1.1,8.8.8.8',
            'enabled' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $device = CpeDevice::where('ppp_user_id', $pppUser->id)->first();
    expect($device->cached_params['wan_connections'][0]['vlan_id'])->toBe(200);
});

it('admin can update wan connection with pppoe credentials', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
        'cached_params' => [
            'wan_connections' => [
                ['key' => '1.1.1', 'ppp_idx' => 1, 'username' => 'old@isp.id'],
            ],
        ],
    ]);

    Http::fake(['*/tasks*' => Http::response(['_id' => null], 200)]);

    $this->actingAs($admin)
        ->putJson(route('cpe.wan-update', [$pppUser->id, 1, 1, 1]), [
            'username' => 'newuser@isp.id',
            'password' => 'newpppoepass',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('wan update returns 422 when no params sent', function () {
    $admin = cpeTenantAdmin();
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($admin)
        ->putJson(route('cpe.wan-update', [$pppUser->id, 1, 1, 1]), [])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('non-admin cannot update wan connection', function () {
    $admin = cpeTenantAdmin();
    $cs = User::factory()->create(['role' => 'cs', 'parent_id' => $admin->id]);
    $pppUser = cpePppUser($admin);
    CpeDevice::create([
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'genieacs_device_id' => 'TEST-DEVICE-001',
    ]);

    $this->actingAs($cs)
        ->putJson(route('cpe.wan-update', [$pppUser->id, 1, 1, 1]), [
            'vlan_id' => 100,
        ])
        ->assertForbidden();
});
