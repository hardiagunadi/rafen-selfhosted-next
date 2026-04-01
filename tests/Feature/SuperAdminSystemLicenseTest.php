<?php

use App\Models\User;
use App\Services\LicenseFingerprintService;
use App\Services\LicenseSignatureService;
use App\Services\ServerHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

    $machineIdPath = storage_path('framework/testing-machine-id');
    File::ensureDirectoryExists(dirname($machineIdPath));
    File::put($machineIdPath, 'machine-id-test');

    $licensePath = storage_path('framework/testing-license.lic');
    File::delete($licensePath);

    config()->set('license.machine_id_path', $machineIdPath);
    config()->set('license.path', $licensePath);
    config()->set('license.public_key_editable', false);

    $keypair = sodium_crypto_sign_keypair();
    config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));

    $this->licenseSecretKey = sodium_crypto_sign_secretkey($keypair);
    $this->licenseSignatureService = app(LicenseSignatureService::class);

    $this->environmentFilename = 'testing-license.env';
    $this->environmentFilePath = base_path($this->environmentFilename);
    File::put($this->environmentFilePath, "APP_NAME=Rafen\n");
    app()->loadEnvironmentFrom($this->environmentFilename);
});

afterEach(function () {
    File::delete((string) config('license.path'));
    File::delete((string) config('license.machine_id_path'));
    File::delete($this->environmentFilePath);
    app()->loadEnvironmentFrom('.env');
});

function createSuperAdminForLicense(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

function makeLicensePayload(object $testCase, array $overrides = []): array
{
    $fingerprint = app(LicenseFingerprintService::class)->generate();

    $payload = array_merge([
        'license_id' => 'RAFEN-SH-2026-0001',
        'customer_name' => 'PT Contoh ISP',
        'instance_name' => 'production',
        'issued_at' => now()->toDateString(),
        'expires_at' => now()->addYear()->toDateString(),
        'support_until' => now()->addYear()->toDateString(),
        'grace_days' => 21,
        'fingerprint' => $fingerprint,
        'domains' => ['billing.example.test'],
        'modules' => ['core', 'mikrotik'],
        'limits' => [
            'max_mikrotik' => 10,
            'max_ppp_users' => 500,
        ],
    ], $overrides);

    $signature = sodium_crypto_sign_detached(
        $testCase->licenseSignatureService->canonicalize($payload),
        $testCase->licenseSecretKey,
    );

    $payload['signature'] = base64_encode($signature);

    return $payload;
}

it('redirects super admin to license page when license is missing', function () {
    $superAdmin = createSuperAdminForLicense();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertRedirect(route('super-admin.settings.license'));
});

it('shows license status page to super admin even when license is missing', function () {
    $superAdmin = createSuperAdminForLicense();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.license'))
        ->assertSuccessful()
        ->assertSee('Lisensi Sistem')
        ->assertSee('Belum Ada Lisensi')
        ->assertDontSee('Public Key Lisensi');
});

it('shows the system license menu near email settings for super admin', function () {
    $superAdmin = createSuperAdminForLicense();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.license'))
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Pengaturan Email',
            'Lisensi Sistem',
        ]);
});

it('accepts a valid uploaded system license', function () {
    $superAdmin = createSuperAdminForLicense();
    $payload = makeLicensePayload($this);
    $serverHealthService = Mockery::mock(ServerHealthService::class);
    $serverHealthService->shouldReceive('startInactiveLicensedServices')->once()->andReturn([
        'attempted' => 1,
        'started' => ['Queue Worker'],
        'already_running' => [],
        'failed' => [],
    ]);
    $this->app->instance(ServerHealthService::class, $serverHealthService);

    $upload = UploadedFile::fake()->createWithContent(
        'rafen.lic',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.license.update'), [
            'license_file' => $upload,
        ])
        ->assertRedirect(route('super-admin.settings.license'))
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Queue Worker'));

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertSuccessful();
});

it('rejects uploaded license with mismatched fingerprint', function () {
    $superAdmin = createSuperAdminForLicense();
    $payload = makeLicensePayload($this, [
        'fingerprint' => 'sha256:wrong-fingerprint',
    ]);

    $upload = UploadedFile::fake()->createWithContent(
        'rafen.lic',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.license.update'), [
            'license_file' => $upload,
        ])
        ->assertRedirect(route('super-admin.settings.license'))
        ->assertSessionHas('error');

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertRedirect(route('super-admin.settings.license'));
});

it('returns not found for the removed self-hosted public key update endpoint', function () {
    $superAdmin = createSuperAdminForLicense();
    $newKeypair = sodium_crypto_sign_keypair();
    $newPublicKey = base64_encode(sodium_crypto_sign_publickey($newKeypair));

    $this->actingAs($superAdmin)
        ->post('/super-admin/settings/license/public-key', [
            'license_public_key' => $newPublicKey,
        ])
        ->assertNotFound();
});

it('keeps saas dashboard accessible when system license table is not migrated', function () {
    config()->set('license.enforce', false);

    Schema::drop('system_licenses');

    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth()->toDateString(),
        'trial_days_remaining' => 0,
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful();
});

it('shows a clear warning on the license page when system license table is not migrated', function () {
    Schema::drop('system_licenses');

    $superAdmin = createSuperAdminForLicense();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.license'))
        ->assertSuccessful()
        ->assertSee('Jalankan php artisan migrate terlebih dahulu.');
});

it('hides self-hosted license ui in saas mode', function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=false');
    putenv('LICENSE_ENFORCE=false');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'false';
    $_ENV['LICENSE_ENFORCE'] = 'false';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'false';
    $_SERVER['LICENSE_ENFORCE'] = 'false';

    $this->refreshApplication();
    $this->artisan('migrate');
    config()->set('license.self_hosted_enabled', false);
    config()->set('license.enforce', false);

    $superAdmin = createSuperAdminForLicense();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('Lisensi Sistem');
});

it('returns not found for self-hosted license page in saas mode', function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=false');
    putenv('LICENSE_ENFORCE=false');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'false';
    $_ENV['LICENSE_ENFORCE'] = 'false';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'false';
    $_SERVER['LICENSE_ENFORCE'] = 'false';

    $this->refreshApplication();
    $this->artisan('migrate');
    config()->set('license.self_hosted_enabled', false);
    config()->set('license.enforce', false);

    $superAdmin = createSuperAdminForLicense();

    $this->actingAs($superAdmin)
        ->get('/super-admin/settings/license')
        ->assertNotFound();
});
