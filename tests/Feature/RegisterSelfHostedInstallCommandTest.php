<?php

use App\Services\LicenseFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=true');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'true';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'true';

    $this->refreshApplication();
    $this->artisan('migrate');

    $this->environmentFilename = 'testing-register-self-hosted-install.env';
    $this->environmentFilePath = base_path($this->environmentFilename);
    File::put($this->environmentFilePath, "APP_NAME=Rafen Self-Hosted\nSELF_HOSTED_REGISTRY_TOKEN=registry-token-002\n");
    app()->loadEnvironmentFrom($this->environmentFilename);

    config()->set('license.self_hosted_enabled', true);
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-002');
    config()->set('app.url', 'http://10.0.0.5');
    config()->set('app.name', 'Rafen Self-Hosted');
});

afterEach(function () {
    File::delete($this->environmentFilePath);
    app()->loadEnvironmentFrom('.env');
});

it('sends install time registration payload to saas from command', function () {
    Http::fake([
        'https://saas.example.test/api/self-hosted/install-registrations' => Http::response([
            'tenant_id' => 77,
            'tenant_name' => 'Install Admin',
            'registry_token' => 'tenant-specific-registry-token-002',
        ], 200),
    ]);

    $this->artisan('self-hosted:register-install', [
        '--admin-name' => 'Install Admin',
        '--admin-email' => 'install@example.test',
        '--admin-phone' => '081234567890',
    ])
        ->expectsOutputToContain('Registrasi install-time self-hosted berhasil dikirim.')
        ->expectsOutputToContain('Registry Token: diperbarui otomatis dari SaaS untuk instance ini.')
        ->assertExitCode(0);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://saas.example.test/api/self-hosted/install-registrations'
            && $request->hasHeader('Authorization', 'Bearer registry-token-002')
            && ($payload['app_url'] ?? null) === 'http://10.0.0.5'
            && ($payload['admin_name'] ?? null) === 'Install Admin'
            && ($payload['admin_email'] ?? null) === 'install@example.test'
            && ($payload['admin_phone'] ?? null) === '081234567890'
            && ($payload['fingerprint'] ?? null) === app(LicenseFingerprintService::class)->generate()
            && ($payload['access_mode'] ?? null) === 'ip-based';
    });

    expect(config('services.self_hosted_registry.token'))->toBe('tenant-specific-registry-token-002')
        ->and(File::get($this->environmentFilePath))->toContain('SELF_HOSTED_REGISTRY_TOKEN=tenant-specific-registry-token-002');
});

it('skips install time registration command gracefully when registry config is missing', function () {
    config()->set('services.self_hosted_registry.url', '');

    $this->artisan('self-hosted:register-install')
        ->expectsOutputToContain('Registrasi install-time dilewati karena SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.')
        ->assertExitCode(0);
});
