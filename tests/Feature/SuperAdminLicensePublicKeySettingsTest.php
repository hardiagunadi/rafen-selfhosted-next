<?php

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\TenantSettings;
use App\Services\LicenseFingerprintService;
use App\Services\LicenseSignatureService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->environmentFilename = 'testing-license-public-key.env';
    $this->environmentFilePath = base_path($this->environmentFilename);
    File::put($this->environmentFilePath, "APP_NAME=Rafen\nLICENSE_PUBLIC_KEY=\n");
    app()->loadEnvironmentFrom($this->environmentFilename);
    config()->set('license.public_key_editable', false);
    config()->set('app.url', 'https://billing.example.test');
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $machineIdPath = storage_path('framework/testing-machine-id-license-public-key');
    File::ensureDirectoryExists(dirname($machineIdPath));
    File::put($machineIdPath, 'machine-id-license-public-key');
    config()->set('license.machine_id_path', $machineIdPath);

    $issuerKeypair = sodium_crypto_sign_keypair();
    $issuerPrivateKeyPath = storage_path('framework/testing-license-public-key-private.key');
    File::put($issuerPrivateKeyPath, base64_encode(sodium_crypto_sign_secretkey($issuerKeypair)));

    config()->set('license.private_key_path', $issuerPrivateKeyPath);
    config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($issuerKeypair)));

    $this->withoutMiddleware(ValidateCsrfToken::class);
});

afterEach(function () {
    File::delete($this->environmentFilePath);
    File::delete((string) config('license.machine_id_path'));
    File::delete((string) config('license.private_key_path'));
    app()->loadEnvironmentFrom('.env');
});

function createSuperAdminForLicensePublicKey(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

function createLicensePresetPlan(array $overrides = []): SubscriptionPlan
{
    return SubscriptionPlan::query()->create(array_merge([
        'name' => 'Starter',
        'slug' => 'starter-'.fake()->unique()->slug(),
        'description' => 'Preset lisensi dari database',
        'price' => 100000,
        'duration_days' => 30,
        'max_mikrotik' => 3,
        'max_ppp_users' => 100,
        'max_vpn_peers' => 1,
        'features' => ['RADIUS Integration'],
        'is_active' => true,
        'is_featured' => false,
        'sort_order' => 1,
    ], $overrides));
}

it('shows the public key license menu on the super admin dashboard', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Pengaturan Email')
        ->assertSee('Public Key Lisensi');
});

it('shows the saas public key settings page', function () {
    createLicensePresetPlan([
        'name' => 'Starter',
        'slug' => 'starter',
        'sort_order' => 1,
    ]);
    createLicensePresetPlan([
        'name' => 'Growth',
        'slug' => 'growth',
        'max_mikrotik' => 10,
        'max_ppp_users' => 1000,
        'max_vpn_peers' => 10,
        'features' => ['RADIUS Integration', 'VPN Access', 'Whatsapp Integration'],
        'sort_order' => 2,
    ]);
    createLicensePresetPlan([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'max_mikrotik' => -1,
        'max_ppp_users' => -1,
        'max_vpn_peers' => -1,
        'features' => ['FreeRADIUS Integration', 'VPN Dedicated', 'Whatsapp Integration', 'OLT Integration', 'GenieACS Integration'],
        'sort_order' => 3,
    ]);

    $superAdmin = createSuperAdminForLicensePublicKey();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.license-public-key'))
        ->assertSuccessful()
        ->assertSee('Public Key Lisensi')
        ->assertSee('Mode Deploy')
        ->assertSee('SaaS')
        ->assertSee('Issue Lisensi Self-Hosted')
        ->assertSee('Preset Lisensi')
        ->assertSee('Starter')
        ->assertSee('Growth')
        ->assertSee('Enterprise');
});

it('updates the saas public key setting', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();
    $keypair = sodium_crypto_sign_keypair();
    $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));
    config()->set('license.public_key_editable', true);

    $this->actingAs($superAdmin)
        ->put(route('super-admin.settings.license-public-key.update'), [
            'license_public_key' => $publicKey,
        ])
        ->assertRedirect(route('super-admin.settings.license-public-key'))
        ->assertSessionHas('success');

    expect(config('license.public_key'))->toBe($publicKey)
        ->and(File::get($this->environmentFilePath))->toContain('LICENSE_PUBLIC_KEY='.$publicKey);
});

it('validates the saas public key format', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();
    config()->set('license.public_key_editable', true);

    $this->actingAs($superAdmin)
        ->from(route('super-admin.settings.license-public-key'))
        ->put(route('super-admin.settings.license-public-key.update'), [
            'license_public_key' => 'invalid-public-key',
        ])
        ->assertRedirect(route('super-admin.settings.license-public-key'))
        ->assertSessionHasErrors(['license_public_key']);
});

it('shows the saas public key page as environment-managed by default', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.license-public-key'))
        ->assertSuccessful()
        ->assertSee('Public key lisensi dikelola melalui environment aplikasi.')
        ->assertSee('LICENSE_PUBLIC_KEY_EDITABLE=false')
        ->assertDontSee('Simpan Public Key');
});

it('forbids updating the saas public key when editing is disabled', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();
    $keypair = sodium_crypto_sign_keypair();
    $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

    $this->actingAs($superAdmin)
        ->put(route('super-admin.settings.license-public-key.update'), [
            'license_public_key' => $publicKey,
        ])
        ->assertForbidden();
});

it('issues and downloads a self-hosted license from the saas ui', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();
    $fingerprint = app(LicenseFingerprintService::class)->generate();

    $response = $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.license-public-key.issue'), [
            'customer_name' => 'PT Contoh ISP',
            'instance_name' => 'production',
            'fingerprint' => $fingerprint,
            'expires_at' => now()->addYear()->toDateString(),
            'support_until' => now()->addYear()->toDateString(),
            'grace_days' => 21,
            'modules' => ['radius', 'vpn'],
            'domains_text' => "billing.example.test\nportal.example.test",
            'max_mikrotik' => 10,
            'max_ppp_users' => 500,
            'additional_limits' => '{"max_radius_clients":20}',
        ]);

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/json');

    expect($response->headers->get('content-disposition'))->toContain('.lic');

    $payload = json_decode($response->streamedContent(), true);
    $signatureService = app(LicenseSignatureService::class);

    expect($payload)
        ->toBeArray()
        ->and($payload['customer_name'])->toBe('PT Contoh ISP')
        ->and($payload['instance_name'])->toBe('production')
        ->and($payload['fingerprint'])->toBe($fingerprint)
        ->and($payload['domains'])->toBe(['billing.example.test', 'portal.example.test'])
        ->and($payload['modules'])->toBe(['radius', 'vpn', 'core'])
        ->and($payload['limits'])->toBe([
            'max_mikrotik' => 10,
            'max_ppp_users' => 500,
            'max_radius_clients' => 20,
        ])
        ->and($signatureService->verify($payload))->toBeTrue();
});

it('creates or updates a saas tenant record when issuing a self-hosted license', function () {
    createLicensePresetPlan([
        'name' => 'Growth',
        'slug' => 'growth',
        'max_mikrotik' => 10,
        'max_ppp_users' => 1000,
        'max_vpn_peers' => 10,
        'features' => ['RADIUS Integration', 'VPN Access', 'Whatsapp Integration'],
        'sort_order' => 2,
    ]);

    $superAdmin = createSuperAdminForLicensePublicKey();
    $fingerprint = app(LicenseFingerprintService::class)->generate();

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.license-public-key.issue'), [
            'license_preset' => 'growth',
            'customer_name' => 'PT Self Hosted',
            'instance_name' => 'edge-jakarta',
            'fingerprint' => $fingerprint,
            'expires_at' => now()->addYear()->toDateString(),
            'max_mikrotik' => 25,
            'max_ppp_users' => 1500,
        ])
        ->assertSuccessful();

    $tenant = User::query()
        ->where('self_hosted_fingerprint', $fingerprint)
        ->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->isSelfHostedInstance())->toBeTrue()
        ->and($tenant->subscription_method)->toBe(User::SUBSCRIPTION_METHOD_LICENSE)
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->self_hosted_instance_name)->toBe('edge-jakarta')
        ->and($tenant->license_max_mikrotik)->toBe(25)
        ->and($tenant->license_max_ppp_users)->toBe(1500)
        ->and($tenant->subscriptionPlan?->slug)->toBe('growth');

    $settings = TenantSettings::query()->where('user_id', $tenant->id)->first();

    expect($settings)->not->toBeNull()
        ->and($settings->admin_subdomain)->toStartWith('sh-')
        ->and($settings->portal_slug)->toBe($settings->admin_subdomain);

    $existingTenantId = $tenant->id;

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.license-public-key.issue'), [
            'license_preset' => 'growth',
            'customer_name' => 'PT Self Hosted Renewed',
            'instance_name' => 'edge-jakarta-2',
            'fingerprint' => $fingerprint,
            'expires_at' => now()->addYears(2)->toDateString(),
            'max_mikrotik' => 30,
            'max_ppp_users' => 2000,
        ])
        ->assertSuccessful();

    $tenant->refresh();

    expect(User::query()->where('self_hosted_fingerprint', $fingerprint)->count())->toBe(1)
        ->and($tenant->id)->toBe($existingTenantId)
        ->and($tenant->name)->toBe('PT Self Hosted Renewed')
        ->and($tenant->self_hosted_instance_name)->toBe('edge-jakarta-2')
        ->and($tenant->license_max_mikrotik)->toBe(30)
        ->and($tenant->license_max_ppp_users)->toBe(2000);
});

it('applies selected preset defaults when issuing a self-hosted license from the saas ui', function () {
    createLicensePresetPlan([
        'name' => 'Growth',
        'slug' => 'growth',
        'max_mikrotik' => 10,
        'max_ppp_users' => 1000,
        'max_vpn_peers' => 10,
        'features' => ['RADIUS Integration', 'VPN Access', 'Whatsapp Integration'],
        'sort_order' => 2,
    ]);

    $superAdmin = createSuperAdminForLicensePublicKey();
    $fingerprint = app(LicenseFingerprintService::class)->generate();

    $response = $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.license-public-key.issue'), [
            'license_preset' => 'growth',
            'customer_name' => 'PT Paket Growth',
            'instance_name' => 'production',
            'fingerprint' => $fingerprint,
            'expires_at' => now()->addYear()->toDateString(),
        ]);

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/json');

    $payload = json_decode($response->streamedContent(), true);

    expect($payload)
        ->toBeArray()
        ->and($payload['customer_name'])->toBe('PT Paket Growth')
        ->and($payload['modules'])->toBe(['core', 'mikrotik', 'radius', 'vpn', 'wa'])
        ->and($payload['grace_days'])->toBe(21)
        ->and($payload['limits'])->toBe([
            'max_mikrotik' => 10,
            'max_ppp_users' => 1000,
            'max_vpn_peers' => 10,
        ]);
});

it('validates self-hosted license issuing form input', function () {
    $superAdmin = createSuperAdminForLicensePublicKey();

    $this->actingAs($superAdmin)
        ->from(route('super-admin.settings.license-public-key'))
        ->post(route('super-admin.settings.license-public-key.issue'), [
            'customer_name' => '',
            'instance_name' => '',
            'fingerprint' => 'invalid',
            'expires_at' => '2026/01/01',
            'additional_limits' => 'not-json',
        ])
        ->assertRedirect(route('super-admin.settings.license-public-key'))
        ->assertSessionHasErrors([
            'customer_name',
            'instance_name',
            'fingerprint',
            'expires_at',
            'additional_limits',
        ]);
});
