<?php

use App\Models\SelfHostedUpdateState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    putenv('APP_ENV=example');
    $_ENV['APP_ENV'] = 'example';
    $_SERVER['APP_ENV'] = 'example';
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=:memory:');
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = ':memory:';
    $_SERVER['DB_CONNECTION'] = 'sqlite';
    $_SERVER['DB_DATABASE'] = ':memory:';
    putenv('LICENSE_SELF_HOSTED_ENABLED=true');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'true';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'true';

    $this->refreshApplication();
    $this->artisan('migrate');

    $this->environmentFilename = 'testing-self-hosted-heartbeat.env';
    $this->environmentFilePath = base_path($this->environmentFilename);
    File::put($this->environmentFilePath, "APP_NAME=Rafen Self-Hosted\nSELF_HOSTED_REGISTRY_TOKEN=registry-token-002\n");
    app()->loadEnvironmentFrom($this->environmentFilename);

    config()->set('license.self_hosted_enabled', true);
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-002');
    config()->set('app.url', 'http://10.0.0.5');
    config()->set('app.name', 'Rafen Self-Hosted');
    config()->set('app.version', '2026.04.01-main.1');
    config()->set('app.commit', 'abc1234');
});

afterEach(function (): void {
    File::delete($this->environmentFilePath);
    app()->loadEnvironmentFrom('.env');
});

it('sends self hosted heartbeat payload to saas from command', function () {
    SelfHostedUpdateState::query()->create([
        'channel' => 'stable',
        'current_version' => '2026.04.01-main.1',
        'current_commit' => 'abc1234',
        'current_ref' => 'v2026.04.01-main.1',
        'latest_version' => '2026.04.04-main.1',
        'latest_commit' => 'bee6dfb',
        'latest_ref' => 'v2026.04.04-main.1',
        'update_available' => true,
        'last_checked_at' => now(),
        'last_check_status' => 'ok',
        'last_check_message' => 'Update tersedia untuk instance ini.',
        'last_applied_at' => now()->subHour(),
        'last_apply_status' => 'failed',
        'last_apply_message' => 'Composer install gagal.',
        'rollback_ref' => '1111111111111111111111111111111111111111',
    ]);

    Http::fake([
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 15,
        ], 200),
    ]);

    $this->artisan('self-hosted:heartbeat')
        ->expectsOutputToContain('Heartbeat self-hosted berhasil dikirim.')
        ->expectsOutputToContain('Current Version: 2026.04.01-main.1')
        ->expectsOutputToContain('Latest Version : 2026.04.04-main.1')
        ->assertExitCode(0);

    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($state)
        ->not->toBeNull()
        ->and($state?->last_heartbeat_status)->toBe('success')
        ->and($state?->last_heartbeat_status_id)->toBe(15)
        ->and($state?->last_heartbeat_message)->toBe('Heartbeat status instance berhasil dikirim ke SaaS.')
        ->and($state?->last_successful_heartbeat_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://saas.example.test/api/self-hosted/heartbeats'
            && $request->hasHeader('Authorization', 'Bearer registry-token-002')
            && ($payload['app_url'] ?? null) === 'http://10.0.0.5'
            && ($payload['current_version'] ?? null) === '2026.04.01-main.1'
            && ($payload['latest_version'] ?? null) === '2026.04.04-main.1'
            && ($payload['last_apply_status'] ?? null) === 'failed'
            && ($payload['rollback_ref'] ?? null) === '1111111111111111111111111111111111111111';
    });
});

it('skips self hosted heartbeat command gracefully when registry config is missing', function () {
    config()->set('services.self_hosted_registry.url', '');

    $this->artisan('self-hosted:heartbeat')
        ->expectsOutputToContain('Heartbeat dilewati karena SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.')
        ->assertExitCode(0);

    expect(SelfHostedUpdateState::query()->count())->toBe(0);
});
