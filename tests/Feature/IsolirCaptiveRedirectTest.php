<?php

use App\Models\MikrotikConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects captive probe requests from mikrotik host to tenant isolir page', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.10',
        'is_active' => true,
        'is_online' => true,
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_HOST' => 'example.org',
    ])->get('/generate_204');

    $response->assertRedirect('/isolir/'.$owner->id);
});

it('does not redirect webhook endpoint even when request comes from mikrotik host', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.11',
        'is_active' => true,
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.11',
        'HTTP_HOST' => 'example.org',
    ])->get('/webhook/wa');

    $response->assertSuccessful();
});

it('does not force isolir redirect on app root path for normal host access', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.12',
        'is_active' => true,
    ]);

    $appHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.12',
        'HTTP_HOST' => $appHost,
    ])->get('/');

    $response->assertRedirect(route('login'));
});

it('redirects captive probe requests from isolated pool ip when source is not mikrotik host', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.20',
        'is_active' => true,
        'is_online' => true,
        'isolir_pool_range' => '10.99.0.2-10.99.0.254',
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '10.99.0.88',
        'HTTP_HOST' => 'example.org',
    ])->get('/generate_204');

    $response->assertRedirect('/isolir/'.$owner->id);
});

it('redirects generate204 wildcard captive path to isolir page', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.30',
        'is_active' => true,
        'is_online' => true,
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.30',
        'HTTP_HOST' => 'example.org',
    ])->get('/generate204_abcdef');

    $response->assertRedirect('/isolir/'.$owner->id);
});

it('redirects direct app host access for clients coming from isolated pool ip', function () {
    config()->set('app.url', 'http://198.51.100.10');

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth(),
    ]);

    MikrotikConnection::factory()->create([
        'owner_id' => $owner->id,
        'host' => '203.0.113.40',
        'is_active' => true,
        'is_online' => true,
        'isolir_pool_range' => '10.99.0.2-10.99.0.254',
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '10.99.0.44',
        'HTTP_HOST' => '198.51.100.10',
    ])->get('/');

    $response->assertRedirect('/isolir/'.$owner->id);
});
