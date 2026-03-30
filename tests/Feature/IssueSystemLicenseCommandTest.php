<?php

use App\Services\LicenseFingerprintService;
use App\Services\LicenseSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=true');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'true';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'true';

    $this->refreshApplication();
    $this->artisan('migrate');

    config()->set('license.self_hosted_enabled', true);
    config()->set('license.enforce', true);
    config()->set('app.url', 'https://billing.example.test');
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $machineIdPath = storage_path('framework/testing-machine-id-issuer');
    File::ensureDirectoryExists(dirname($machineIdPath));
    File::put($machineIdPath, 'machine-id-issuer');

    $licensePath = storage_path('framework/testing-issued-license.lic');
    File::delete($licensePath);

    config()->set('license.machine_id_path', $machineIdPath);
    config()->set('license.path', $licensePath);

    $issuerKeypair = sodium_crypto_sign_keypair();
    $issuerPrivateKeyPath = storage_path('framework/testing-license-issuer-private.key');

    File::put($issuerPrivateKeyPath, base64_encode(sodium_crypto_sign_secretkey($issuerKeypair)));

    config()->set('license.private_key_path', $issuerPrivateKeyPath);
    config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($issuerKeypair)));
});

afterEach(function () {
    File::delete((string) config('license.path'));
    File::delete((string) config('license.machine_id_path'));
    File::delete((string) config('license.private_key_path'));
});

it('issues a signed system license that can be validated by the application', function () {
    $fingerprint = app(LicenseFingerprintService::class)->generate();

    $this->artisan('license:issue', [
        'customer_name' => 'PT Contoh ISP',
        'instance_name' => 'production',
        'fingerprint' => $fingerprint,
        'expires_at' => now()->addYear()->toDateString(),
        '--support-until' => now()->addYear()->toDateString(),
        '--module' => ['vpn', 'radius'],
        '--domain' => ['billing.example.test'],
        '--limit' => ['max_mikrotik=10', 'max_ppp_users=500'],
        '--path' => (string) config('license.path'),
    ])
        ->expectsOutputToContain('File lisensi berhasil dibuat.')
        ->expectsOutputToContain('Path        : '.config('license.path'))
        ->assertExitCode(0);

    $payload = json_decode((string) File::get((string) config('license.path')), true);
    $signatureService = app(LicenseSignatureService::class);

    expect(File::exists((string) config('license.path')))->toBeTrue()
        ->and($payload)->toBeArray()
        ->and($payload['customer_name'])->toBe('PT Contoh ISP')
        ->and($payload['instance_name'])->toBe('production')
        ->and($payload['fingerprint'])->toBe($fingerprint)
        ->and($payload['modules'])->toBe(['vpn', 'radius', 'core'])
        ->and($payload['limits'])->toBe([
            'max_mikrotik' => 10,
            'max_ppp_users' => 500,
        ])
        ->and($signatureService->verify($payload))->toBeTrue();
});

it('refuses to issue a license when issuer private key does not match configured public key', function () {
    $mismatchedKeypair = sodium_crypto_sign_keypair();
    File::put((string) config('license.private_key_path'), base64_encode(sodium_crypto_sign_secretkey($mismatchedKeypair)));

    $this->artisan('license:issue', [
        'customer_name' => 'PT Tidak Cocok',
        'instance_name' => 'production',
        'fingerprint' => app(LicenseFingerprintService::class)->generate(),
        'expires_at' => now()->addYear()->toDateString(),
        '--path' => (string) config('license.path'),
    ])
        ->expectsOutputToContain('Private key issuer tidak cocok dengan LICENSE_PUBLIC_KEY pada server SaaS ini.')
        ->assertExitCode(1);

    expect(File::exists((string) config('license.path')))->toBeFalse();
});
