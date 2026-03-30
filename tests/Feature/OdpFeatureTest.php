<?php

use App\Models\Odp;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows super admin to manage odp data', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $tenant = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('odps.index'))
        ->assertSuccessful();

    $this->actingAs($superAdmin)
        ->post(route('odps.store'), [
            'owner_id' => $tenant->id,
            'code' => 'ODP-001',
            'name' => 'ODP Pusat',
            'area' => 'Kota',
            'latitude' => '-7.1234567',
            'longitude' => '109.1234567',
            'capacity_ports' => 16,
            'status' => 'active',
        ])
        ->assertRedirect(route('odps.index'));

    expect(Odp::query()->where('code', 'ODP-001')->exists())->toBeTrue();
});

it('allows teknisi to open create odp page', function () {
    $tenant = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'is_super_admin' => false,
        'role' => 'teknisi',
    ]);

    $this->actingAs($teknisi)
        ->get(route('odps.create'))
        ->assertSuccessful();
});

it('generates next odp code by owner and wilayah prefix', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $tenant = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
    ]);

    Odp::factory()->create([
        'owner_id' => $tenant->id,
        'code' => 'WONOSOBO-KALIBOTO-001',
    ]);

    Odp::factory()->create([
        'owner_id' => $tenant->id,
        'code' => 'WONOSOBO-KALIBOTO-002',
    ]);

    Odp::factory()->create([
        'owner_id' => $tenant->id,
        'code' => 'WONOSOBO-KALIANGET-001',
    ]);

    $this->actingAs($superAdmin)
        ->getJson(route('odps.generate-code', [
            'owner_id' => $tenant->id,
            'location_code' => 'Wonosobo',
            'area_name' => 'Kaliboto',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('code', 'WONOSOBO-KALIBOTO-003');
});

it('prevents teknisi from generating odp code for other tenant owner', function () {
    $tenantA = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $tenantB = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisiTenantA = User::factory()->create([
        'parent_id' => $tenantA->id,
        'is_super_admin' => false,
        'role' => 'teknisi',
    ]);

    $this->actingAs($teknisiTenantA)
        ->getJson(route('odps.generate-code', [
            'owner_id' => $tenantB->id,
            'location_code' => 'Wonosobo',
            'area_name' => 'Kaliboto',
        ]))
        ->assertForbidden();
});

it('allows teknisi to edit and delete odp without pelanggan', function () {
    $tenant = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'is_super_admin' => false,
        'role' => 'teknisi',
    ]);

    $odp = Odp::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    $this->actingAs($teknisi)
        ->get(route('odps.edit', $odp))
        ->assertSuccessful();

    $this->actingAs($teknisi)
        ->put(route('odps.update', $odp), [
            'owner_id' => $tenant->id,
            'code' => 'ODP-TEK-EDIT',
            'name' => 'ODP Teknisi',
            'area' => 'Area Teknisi',
            'latitude' => '-7.1111111',
            'longitude' => '109.1111111',
            'capacity_ports' => 12,
            'status' => 'active',
        ])
        ->assertRedirect(route('odps.index'));

    $this->actingAs($teknisi)
        ->deleteJson(route('odps.destroy', $odp))
        ->assertSuccessful()
        ->assertJsonPath('status', 'Data ODP dihapus.');
});

it('prevents deleting odp when pelanggan already attached', function () {
    $tenant = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'is_super_admin' => false,
        'role' => 'teknisi',
    ]);

    $odp = Odp::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    PppUser::query()->create([
        'owner_id' => $tenant->id,
        'odp_id' => $odp->id,
        'customer_name' => 'Pelanggan Uji',
    ]);

    $this->actingAs($teknisi)
        ->deleteJson(route('odps.destroy', $odp))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'ODP tidak bisa dihapus karena sudah terhubung ke data pelanggan.');
});

it('updates odp_pop automatically from selected odp when creating ppp user', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $group = ProfileGroup::factory()->create();
    $profile = PppProfile::query()->create([
        'owner_id' => $superAdmin->id,
        'profile_group_id' => $group->id,
        'name' => 'Paket 20Mbps',
    ]);

    $odp = Odp::factory()->create([
        'owner_id' => $superAdmin->id,
        'code' => 'ODP-100',
        'name' => 'ODP Timur',
        'status' => 'active',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('ppp-users.store'), [
            'status_registrasi' => 'aktif',
            'tipe_pembayaran' => 'prepaid',
            'status_bayar' => 'sudah_bayar',
            'status_akun' => 'enable',
            'owner_id' => $superAdmin->id,
            'ppp_profile_id' => $profile->id,
            'tipe_service' => 'pppoe',
            'aksi_jatuh_tempo' => 'isolir',
            'tipe_ip' => 'dhcp',
            'odp_id' => $odp->id,
            'odp_pop' => '',
            'customer_id' => 'CUST-ODP-001',
            'customer_name' => 'Pelanggan ODP',
            'nik' => '1234567890123456',
            'nomor_hp' => '081234567890',
            'email' => 'pelanggan.odp@example.test',
            'alamat' => 'Alamat Pelanggan',
            'latitude' => '-7.1234567',
            'longitude' => '109.1234567',
            'location_accuracy_m' => 6.5,
            'location_capture_method' => 'gps',
            'metode_login' => 'username_password',
            'username' => 'pelanggan_odp',
            'ppp_password' => 'secret123',
        ])
        ->assertRedirect(route('ppp-users.index'));

    $pppUser = PppUser::query()->where('username', 'pelanggan_odp')->firstOrFail();

    expect($pppUser->odp_id)->toBe($odp->id)
        ->and($pppUser->odp_pop)->toBe('ODP-100')
        ->and($pppUser->location_capture_method)->toBe('gps')
        ->and((float) $pppUser->location_accuracy_m)->toBe(6.5);
});

it('shows customer map page', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('customer-map.index'))
        ->assertSuccessful()
        ->assertSee('Peta ODP dan Pelanggan PPP');
});

it('returns coverage tile list when tenant map cache is enabled', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    TenantSettings::getOrCreate($superAdmin->id)->update([
        'map_cache_enabled' => true,
        'map_cache_center_lat' => -7.3633000,
        'map_cache_center_lng' => 109.9010000,
        'map_cache_radius_km' => 0.6,
        'map_cache_min_zoom' => 14,
        'map_cache_max_zoom' => 14,
        'map_cache_version' => 3,
    ]);

    $response = $this->actingAs($superAdmin)
        ->get(route('customer-map.cache-tiles'));

    $response->assertSuccessful()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('cache_name', 'tenant-map-'.$superAdmin->id.'-v3');

    $urls = $response->json('urls');
    expect($urls)->toBeArray()->not->toBeEmpty();
});

it('auto disables coverage cache when all tenant odps already geocoded', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    TenantSettings::getOrCreate($superAdmin->id)->update([
        'map_cache_enabled' => true,
        'map_cache_center_lat' => -7.3600000,
        'map_cache_center_lng' => 109.9000000,
        'map_cache_radius_km' => 1.2,
        'map_cache_min_zoom' => 14,
        'map_cache_max_zoom' => 16,
        'map_cache_version' => 4,
    ]);

    Odp::factory()->count(2)->create([
        'owner_id' => $superAdmin->id,
        'latitude' => -7.3611111,
        'longitude' => 109.9011111,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('customer-map.cache-config'))
        ->assertSuccessful()
        ->assertJsonPath('auto_disabled', true)
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('all_odps_geocoded', true);

    $settings = TenantSettings::query()->where('user_id', $superAdmin->id)->firstOrFail();

    expect($settings->map_cache_enabled)->toBeFalse()
        ->and($settings->map_cache_version)->toBe(5);
});

it('keeps teknisi scoped to their own tenant data', function () {
    $tenantA = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $tenantB = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisiTenantA = User::factory()->create([
        'parent_id' => $tenantA->id,
        'is_super_admin' => false,
        'role' => 'teknisi',
    ]);

    $odpTenantA = Odp::factory()->create([
        'owner_id' => $tenantA->id,
    ]);

    $odpTenantB = Odp::factory()->create([
        'owner_id' => $tenantB->id,
    ]);

    $this->actingAs($teknisiTenantA)
        ->get(route('odps.edit', $odpTenantA))
        ->assertSuccessful();

    $this->actingAs($teknisiTenantA)
        ->get(route('odps.edit', $odpTenantB))
        ->assertForbidden();
});
