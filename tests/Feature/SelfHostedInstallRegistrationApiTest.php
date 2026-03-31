<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=false');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'false';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'false';

    $this->refreshApplication();
    $this->artisan('migrate');
    $this->withoutMiddleware(ValidateCsrfToken::class);

    config()->set('license.self_hosted_enabled', false);
    config()->set('services.self_hosted_registry.token', 'registry-token-001');
});

it('registers a self hosted install into saas tenant registry', function () {
    $payload = [
        'app_name' => 'Rafen Self-Hosted',
        'app_url' => 'https://edge.example.test',
        'app_env' => 'production',
        'generated_at' => now()->toIso8601String(),
        'server_name' => 'edge-jakarta',
        'fingerprint' => 'sha256:'.str_repeat('a', 64),
        'current_license_status' => 'missing',
        'current_license_id' => null,
        'admin_name' => 'Install Admin',
        'admin_email' => 'install@example.test',
        'access_mode' => 'domain-based',
    ];

    $this->withHeader('Authorization', 'Bearer registry-token-001')
        ->postJson(route('api.self-hosted.install-registrations'), $payload)
        ->assertSuccessful()
        ->assertJsonPath('tenant_name', 'Install Admin');

    $tenant = User::query()->where('self_hosted_fingerprint', $payload['fingerprint'])->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->isSelfHostedInstance())->toBeTrue()
        ->and($tenant->subscription_method)->toBe(User::SUBSCRIPTION_METHOD_LICENSE)
        ->and($tenant->subscription_status)->toBe('suspended')
        ->and($tenant->self_hosted_instance_name)->toBe('edge-jakarta')
        ->and($tenant->self_hosted_app_url)->toBe('https://edge.example.test');
});

it('updates the existing self hosted registry tenant when the same fingerprint registers again', function () {
    User::query()->create([
        'name' => 'Existing Install',
        'email' => 'selfhosted+existing@tenant.rafen.local',
        'password' => bcrypt('secret-123'),
        'role' => 'administrator',
        'is_super_admin' => false,
        'is_self_hosted_instance' => true,
        'subscription_status' => 'suspended',
        'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
        'self_hosted_fingerprint' => 'sha256:'.str_repeat('b', 64),
    ]);

    $payload = [
        'app_name' => 'Rafen Self-Hosted',
        'app_url' => 'http://10.10.10.2',
        'server_name' => 'edge-surabaya',
        'fingerprint' => 'sha256:'.str_repeat('b', 64),
        'admin_name' => 'Updated Admin',
        'admin_email' => 'updated@example.test',
    ];

    $this->withHeader('Authorization', 'Bearer registry-token-001')
        ->postJson(route('api.self-hosted.install-registrations'), $payload)
        ->assertSuccessful();

    $tenant = User::query()->where('self_hosted_fingerprint', $payload['fingerprint'])->first();

    expect(User::query()->where('self_hosted_fingerprint', $payload['fingerprint'])->count())->toBe(1)
        ->and($tenant)->not->toBeNull()
        ->and($tenant->self_hosted_instance_name)->toBe('edge-surabaya')
        ->and($tenant->self_hosted_app_url)->toBe('http://10.10.10.2');
});

it('rejects self hosted install registration when bearer token is invalid', function () {
    $this->withHeader('Authorization', 'Bearer wrong-token')
        ->postJson(route('api.self-hosted.install-registrations'), [
            'fingerprint' => 'sha256:'.str_repeat('c', 64),
        ])
        ->assertForbidden();
});
