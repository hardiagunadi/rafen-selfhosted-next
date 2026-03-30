<?php

use App\Models\SystemLicense;
use App\Models\User;
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

    $machineIdPath = storage_path('framework/testing-machine-id-stage-two');
    File::ensureDirectoryExists(dirname($machineIdPath));
    File::put($machineIdPath, 'machine-id-stage-two');

    $licensePath = storage_path('framework/testing-license-stage-two.lic');
    File::delete($licensePath);

    config()->set('license.machine_id_path', $machineIdPath);
    config()->set('license.path', $licensePath);

    $keypair = sodium_crypto_sign_keypair();
    config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));

    $this->licenseSecretKey = sodium_crypto_sign_secretkey($keypair);
    $this->licenseSignatureService = app(LicenseSignatureService::class);
});

afterEach(function () {
    File::delete((string) config('license.path'));
    File::delete((string) config('license.machine_id_path'));
});

function createSuperAdminForStageTwo(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

function createTenantAdminForStageTwo(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addMonth()->toDateString(),
        'trial_days_remaining' => 0,
    ]);
}

function stageTwoLicensePayload(object $testCase, array $modules = ['core', 'wa', 'radius', 'vpn', 'olt', 'genieacs']): array
{
    $payload = [
        'license_id' => 'RAFEN-SH-2026-0002',
        'customer_name' => 'PT Tahap Dua',
        'instance_name' => 'production',
        'issued_at' => now()->toDateString(),
        'expires_at' => now()->addYear()->toDateString(),
        'support_until' => now()->addYear()->toDateString(),
        'grace_days' => 21,
        'fingerprint' => app(LicenseFingerprintService::class)->generate(),
        'domains' => ['billing.example.test'],
        'modules' => $modules,
        'limits' => [
            'max_mikrotik' => 10,
            'max_ppp_users' => 500,
        ],
    ];

    $payload['signature'] = base64_encode(sodium_crypto_sign_detached(
        $testCase->licenseSignatureService->canonicalize($payload),
        $testCase->licenseSecretKey,
    ));

    return $payload;
}

function writeStageTwoLicense(object $testCase, array $modules = ['core', 'wa', 'radius', 'vpn', 'olt', 'genieacs']): void
{
    File::put(
        (string) config('license.path'),
        json_encode(stageTwoLicensePayload($testCase, $modules), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

it('prints system license status as json', function () {
    writeStageTwoLicense($this);

    $this->artisan('license:status --json')
        ->assertExitCode(0);
});

it('generates activation request file from command', function () {
    $outputPath = storage_path('framework/license-activation-request.json');
    File::delete($outputPath);

    $this->artisan("license:activation-request --path={$outputPath}")
        ->assertExitCode(0);

    expect(File::exists($outputPath))->toBeTrue();

    $payload = json_decode((string) File::get($outputPath), true);

    expect($payload)
        ->toBeArray()
        ->and($payload['fingerprint'])->toBe(app(LicenseFingerprintService::class)->generate())
        ->and($payload['app_url'])->toBe('https://billing.example.test');

    File::delete($outputPath);
});

it('refreshes system license from disk using command', function () {
    writeStageTwoLicense($this, ['core', 'vpn']);

    $this->artisan('license:refresh')
        ->assertExitCode(0);

    $license = SystemLicense::query()->first();

    expect($license)->not->toBeNull()
        ->and($license->status)->toBe('active')
        ->and($license->license_id)->toBe('RAFEN-SH-2026-0002');
});

it('downloads activation request from super admin page', function () {
    $superAdmin = createSuperAdminForStageTwo();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.license.activation-request'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/json');
});

it('blocks access to gated system feature when module is absent', function () {
    writeStageTwoLicense($this, ['core']);
    $superAdmin = createSuperAdminForStageTwo();

    $this->actingAs($superAdmin)
        ->get(route('settings.wg'))
        ->assertForbidden();
});

it('allows access to gated system feature when module is present', function () {
    writeStageTwoLicense($this, ['core', 'vpn']);
    $superAdmin = createSuperAdminForStageTwo();

    $this->actingAs($superAdmin)
        ->get(route('settings.wg'))
        ->assertSuccessful();
});

it('hides menu items for modules that are not licensed', function () {
    writeStageTwoLicense($this, ['core']);
    $superAdmin = createSuperAdminForStageTwo();
    $tenantAdmin = createTenantAdminForStageTwo();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('WA Gateway');

    $this->actingAs($tenantAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('WireGuard');
});

it('shows menu items for modules that are licensed', function () {
    writeStageTwoLicense($this, ['core', 'wa', 'radius', 'vpn']);
    $superAdmin = createSuperAdminForStageTwo();
    $tenantAdmin = createTenantAdminForStageTwo();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('WA Gateway');

    $this->actingAs($tenantAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('WireGuard');
});
