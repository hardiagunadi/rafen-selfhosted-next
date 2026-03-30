<?php

use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makePwaTenant(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function seedPwaTenantSettings(User $tenant): TenantSettings
{
    Storage::fake('public');
    $tenantSlug = 'tmd-'.$tenant->id;

    $logoPath = UploadedFile::fake()
        ->image('tenant-logo.png', 174, 113)
        ->store('business-logos', 'public');

    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'business_name' => 'Tunas Media Data',
        'business_logo' => $logoPath,
        'admin_subdomain' => $tenantSlug,
        'portal_slug' => $tenantSlug,
    ]);

    return $settings->fresh();
}

it('uses the full tenant name and square icon for the admin manifest', function () {
    $tenant = makePwaTenant();
    seedPwaTenantSettings($tenant);

    $manifestResponse = $this->actingAs($tenant)->get(route('manifest.admin'));

    $manifestResponse->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($manifestResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Tunas Media Data Admin')
        ->and($manifest['short_name'])->toBe('Tunas Media Data Admin')
        ->and($manifest['icons'][0]['src'])->toBe(route('manifest.admin.icon', ['size' => 192]))
        ->and($manifest['icons'][1]['src'])->toBe(route('manifest.admin.icon', ['size' => 512]));

    $iconResponse = $this->actingAs($tenant)->get(route('manifest.admin.icon', ['size' => 192]));

    $iconResponse->assertOk()
        ->assertHeader('content-type', 'image/png');

    $iconPath = $iconResponse->baseResponse->getFile()->getPathname();
    $dimensions = getimagesize($iconPath);

    expect($dimensions[0])->toBe(192)
        ->and($dimensions[1])->toBe(192);
});

it('uses the full tenant name and square icon for the portal manifest', function () {
    $tenant = makePwaTenant();
    $settings = seedPwaTenantSettings($tenant);

    $manifestResponse = $this->get(route('portal.manifest', ['slug' => $settings->portal_slug]));

    $manifestResponse->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($manifestResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Tunas Media Data Portal')
        ->and($manifest['short_name'])->toBe('Tunas Media Data Portal')
        ->and($manifest['icons'][0]['src'])->toBe(route('portal.icon', ['size' => 192, 'slug' => $settings->portal_slug]))
        ->and($manifest['icons'][1]['src'])->toBe(route('portal.icon', ['size' => 512, 'slug' => $settings->portal_slug]));

    $iconResponse = $this->get(route('portal.icon', ['size' => 192, 'slug' => $settings->portal_slug]));

    $iconResponse->assertOk()
        ->assertHeader('content-type', 'image/png');

    $iconPath = $iconResponse->baseResponse->getFile()->getPathname();
    $dimensions = getimagesize($iconPath);

    expect($dimensions[0])->toBe(192)
        ->and($dimensions[1])->toBe(192);
});

it('falls back to the tenant company name when business name is blank', function () {
    $tenant = User::factory()->create([
        'name' => 'Pemilik Tenant',
        'company_name' => 'Tenant Baru',
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::create([
        'user_id' => $tenant->id,
        'business_name' => '',
        'admin_subdomain' => 'tenant-baru-'.$tenant->id,
        'portal_slug' => 'tenant-baru-'.$tenant->id,
        'invoice_prefix' => 'INV',
        'enable_manual_payment' => true,
        'payment_expiry_hours' => 24,
        'auto_isolate_unpaid' => true,
        'grace_period_days' => 3,
    ]);

    $adminManifest = json_decode(
        $this->actingAs($tenant)->get(route('manifest.admin'))->getContent(),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    $portalManifest = json_decode(
        $this->get(route('portal.manifest', ['slug' => 'tenant-baru-'.$tenant->id]))->getContent(),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($adminManifest['name'])->toBe('Tenant Baru Admin')
        ->and($portalManifest['name'])->toBe('Tenant Baru Portal');
});

it('replaces admin and portal branding with the tenant logo when available', function () {
    $tenant = makePwaTenant();
    $settings = seedPwaTenantSettings($tenant);

    $adminResponse = $this->actingAs($tenant)->get(route('tenant-settings.index'));

    $adminResponse->assertOk()
        ->assertSee(asset('storage/'.$settings->business_logo), false)
        ->assertSee(route('manifest.admin.icon', ['size' => 32]), false)
        ->assertSee(route('manifest.admin.icon', ['size' => 180]), false)
        ->assertDontSee(asset('branding/rafen-mark.svg'), false);

    $portalResponse = $this->get(route('portal.login', ['slug' => $settings->portal_slug]));

    $portalResponse->assertOk()
        ->assertSee('storage/'.$settings->business_logo, false)
        ->assertSee(route('portal.icon', ['size' => 32, 'slug' => $settings->portal_slug]), false)
        ->assertSee(route('portal.icon', ['size' => 180, 'slug' => $settings->portal_slug]), false)
        ->assertSee('box-shadow: 0 14px 30px rgba(2,12,27,.35)', false);

    $this->actingAs($tenant)
        ->get(route('manifest.admin.icon', ['size' => 32]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $this->get(route('portal.icon', ['size' => 180, 'slug' => $settings->portal_slug]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('renders install fallback guidance for admin and portal pwa banners', function () {
    $tenant = makePwaTenant();
    $settings = seedPwaTenantSettings($tenant);

    $this->actingAs($tenant)
        ->get(route('tenant-settings.index'))
        ->assertOk()
        ->assertSee('pwa-install-message', false)
        ->assertSee('Pasang Rafen Manager di perangkat Anda', false)
        ->assertSee('function syncPushSubscriptionState(showInvite)', false)
        ->assertSee("document.addEventListener('visibilitychange'", false)
        ->assertSee("window.addEventListener('focus'", false)
        ->assertDontSee('Tambahkan ke layar utama', false);

    $this->get(route('portal.login', ['slug' => $settings->portal_slug]))
        ->assertOk()
        ->assertSee('pwa-portal-message', false)
        ->assertSee('Pasang Portal Pelanggan di HP Anda', false)
        ->assertDontSee('Tambahkan ke layar utama', false);
});

it('prevents stale portal login pages from reusing an expired csrf token', function () {
    $tenant = makePwaTenant();
    $settings = seedPwaTenantSettings($tenant);

    $response = $this->get(route('portal.login', ['slug' => $settings->portal_slug]));
    $cacheControl = (string) $response->headers->get('Cache-Control');

    $response->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT')
        ->assertSee("window.addEventListener('pageshow'", false)
        ->assertSee("navigationEntry?.type === 'back_forward'", false)
        ->assertSee('window.location.reload()', false);

    expect($cacheControl)->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
});

it('adds a solid background when generating icons from transparent logos', function () {
    Storage::fake('public');

    $tenant = makePwaTenant();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $tenantSlug = 'tmd-'.$tenant->id;

    $source = imagecreatetruecolor(160, 80);
    imagealphablending($source, false);
    imagesavealpha($source, true);

    $transparent = imagecolorallocatealpha($source, 0, 0, 0, 127);
    imagefill($source, 0, 0, $transparent);

    $darkFill = imagecolorallocatealpha($source, 24, 34, 56, 0);
    imagefilledrectangle($source, 40, 20, 120, 60, $darkFill);

    ob_start();
    imagepng($source);
    $transparentLogoBinary = (string) ob_get_clean();

    imagedestroy($source);

    $logoPath = 'business-logos/transparent-logo.png';
    Storage::disk('public')->put($logoPath, $transparentLogoBinary);

    $settings->update([
        'business_name' => 'Tunas Media Data',
        'business_logo' => $logoPath,
        'admin_subdomain' => $tenantSlug,
        'portal_slug' => $tenantSlug,
    ]);

    $iconResponse = $this->actingAs($tenant)->get(route('manifest.admin.icon', ['size' => 192]));

    $iconResponse->assertOk()
        ->assertHeader('content-type', 'image/png');

    $iconPath = $iconResponse->baseResponse->getFile()->getPathname();
    $generatedIcon = imagecreatefrompng($iconPath);

    expect($generatedIcon)->not->toBeFalse();

    $cornerPixel = imagecolorsforindex($generatedIcon, imagecolorat($generatedIcon, 8, 8));

    imagedestroy($generatedIcon);

    expect($cornerPixel['alpha'])->toBe(0)
        ->and($cornerPixel['red'])->toBeGreaterThan(220)
        ->and($cornerPixel['green'])->toBeGreaterThan(220)
        ->and($cornerPixel['blue'])->toBeGreaterThan(220);
});

it('uses a smarter notification click handler in admin and portal service workers', function () {
    $adminWorker = file_get_contents(public_path('sw.js'));
    $portalWorker = file_get_contents(public_path('sw-portal.js'));

    expect($adminWorker)->not->toBeFalse()
        ->and($portalWorker)->not->toBeFalse()
        ->and($adminWorker)->toContain('focusOrOpenNotificationTarget')
        ->and($adminWorker)->toContain('findBestClient')
        ->and($adminWorker)->toContain('new URL(rawTargetUrl, self.location.origin)')
        ->and($portalWorker)->toContain('focusOrOpenNotificationTarget')
        ->and($portalWorker)->toContain('findBestClient')
        ->and($portalWorker)->toContain("clientUrl.pathname.startsWith('/portal/')");
});
