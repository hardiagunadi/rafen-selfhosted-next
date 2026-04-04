<?php

use App\Models\MikrotikConnection;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows ready tenant and ip-only note in isolir audit command', function () {
    config()->set('app.url', 'http://198.51.100.10');

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'auto_isolate_unpaid' => true,
        'business_phone' => '08123456789',
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'NAS A',
        'is_active' => true,
        'is_online' => true,
        'isolir_pool_name' => 'pool-isolir',
        'isolir_pool_range' => '10.99.0.2-10.99.0.254',
        'isolir_gateway' => '10.99.0.1',
        'isolir_profile_name' => 'isolir-pppoe',
        'isolir_rate_limit' => '128k/128k',
    ]);

    $this->artisan('billing:audit-isolir --owner-id='.$owner->id)
        ->expectsOutputToContain('Access Mode  : ip-only')
        ->expectsOutputToContain('Mode IP-only terdeteksi')
        ->expectsOutputToContain('Status              : READY')
        ->expectsOutputToContain('Ringkasan: 1 tenant ready, 0 tenant perlu pengecekan.')
        ->assertExitCode(0);
});

it('shows warnings when tenant isolir configuration is incomplete', function () {
    config()->set('app.url', 'http://198.51.100.10');

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'auto_isolate_unpaid' => false,
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'NAS B',
        'is_active' => true,
        'is_online' => true,
        'isolir_pool_name' => '',
        'isolir_pool_range' => '',
        'isolir_gateway' => '',
        'isolir_profile_name' => '',
        'isolir_rate_limit' => '',
    ]);

    $this->artisan('billing:audit-isolir --owner-id='.$owner->id)
        ->expectsOutputToContain('Status              : NEEDS CHECK')
        ->expectsOutputToContain('auto_isolate_unpaid nonaktif')
        ->expectsOutputToContain('NAS "NAS B" belum lengkap')
        ->expectsOutputToContain('Ringkasan: 0 tenant ready, 1 tenant perlu pengecekan.')
        ->assertExitCode(0);
});
