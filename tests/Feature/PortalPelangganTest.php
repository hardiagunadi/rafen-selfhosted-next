<?php

use App\Models\CpeDevice;
use App\Models\Invoice;
use App\Models\PortalSession;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function portalTenant(string $slug = 'test-isp'): User
{
    static $counter = 0;
    $counter++;
    $uniqueSlug = $counter > 1 ? $slug.'-'.$counter : $slug;

    $user = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $settings = TenantSettings::getOrCreate($user->id);
    $settings->update(['portal_slug' => $uniqueSlug]);

    return $user;
}

function makePppUser(int $ownerId, array $attrs = []): PppUser
{
    return PppUser::create(array_merge([
        'owner_id' => $ownerId,
        'username' => 'usertest_'.Str::random(6),
        'ppp_password' => 'pass123',
        'nomor_hp' => '6281234567890',
        'customer_name' => 'Budi',
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'password_clientarea' => 'clientpass',
        'tipe_service' => 'pppoe',
    ], $attrs));
}

function makePortalSession(int $pppUserId): string
{
    $token = Str::random(64);
    PortalSession::create([
        'ppp_user_id' => $pppUserId,
        'token' => $token,
        'ip_address' => '127.0.0.1',
        'last_activity_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);

    return $token;
}

function tenantSlug(User $tenant): string
{
    return TenantSettings::where('user_id', $tenant->id)->value('portal_slug');
}

function portalRouteParams(string $slug): array
{
    return ['slug' => $slug];
}

// ── Login page ─────────────────────────────────────────────────────────────────

it('shows portal login page', function () {
    $tenant = portalTenant('isp-login-page');
    $slug = tenantSlug($tenant);

    $this->get(route('portal.login', portalRouteParams($slug)))->assertOk()->assertSee('Login Portal Pelanggan');
});

// ── Login: plain text password ─────────────────────────────────────────────────

it('can login with plain text password_clientarea', function () {
    $tenant = portalTenant('isp-plain');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, [
        'nomor_hp' => '6281000000001',
        'password_clientarea' => 'mysecret',
    ]);

    $this->post(route('portal.login.post', portalRouteParams($slug)), [
        'nomor_hp' => '081000000001',
        'password' => 'mysecret',
    ])->assertRedirect(route('portal.dashboard', portalRouteParams($slug)));

    expect(PortalSession::where('ppp_user_id', $ppp->id)->exists())->toBeTrue();
    expect(PortalSession::where('ppp_user_id', $ppp->id)->first()?->expires_at?->gt(now()->addDays(29)))->toBeTrue();
});

// ── Login: hashed password ─────────────────────────────────────────────────────

it('can login with hashed password_clientarea', function () {
    $tenant = portalTenant('isp-hashed');
    $slug = tenantSlug($tenant);
    makePppUser($tenant->id, [
        'nomor_hp' => '6281000000002',
        'password_clientarea' => Hash::make('hashed_pass'),
    ]);

    $this->post(route('portal.login.post', portalRouteParams($slug)), [
        'nomor_hp' => '6281000000002',
        'password' => 'hashed_pass',
    ])->assertRedirect(route('portal.dashboard', portalRouteParams($slug)));
});

// ── Login: wrong password ─────────────────────────────────────────────────────

it('rejects login with wrong password', function () {
    $tenant = portalTenant('isp-wrong-pw');
    $slug = tenantSlug($tenant);
    makePppUser($tenant->id, [
        'nomor_hp' => '6281000000003',
        'password_clientarea' => 'correct',
    ]);

    $this->post(route('portal.login.post', portalRouteParams($slug)), [
        'nomor_hp' => '6281000000003',
        'password' => 'wrong',
    ])->assertRedirect()->assertSessionHasErrors('password');

    expect(PortalSession::count())->toBe(0);
});

// ── Login: unknown phone ───────────────────────────────────────────────────────

it('rejects login with unknown phone number', function () {
    $tenant = portalTenant('isp-unknown-phone');
    $slug = tenantSlug($tenant);

    $this->post(route('portal.login.post', portalRouteParams($slug)), [
        'nomor_hp' => '6289999999999',
        'password' => 'anything',
    ])->assertRedirect()->assertSessionHasErrors('nomor_hp');
});

// ── Login: invalid slug 404 ────────────────────────────────────────────────────

it('returns 404 for unknown portal slug', function () {
    $this->get(route('portal.login', portalRouteParams('slug-tidak-ada')))->assertNotFound();
});

// ── Middleware: unauthenticated redirected ─────────────────────────────────────

it('redirects unauthenticated portal request to login', function () {
    $tenant = portalTenant('isp-unauth');
    $slug = tenantSlug($tenant);

    $this->get(route('portal.dashboard', portalRouteParams($slug)))->assertRedirect(route('portal.login', portalRouteParams($slug)));
    $this->get(route('portal.invoices', portalRouteParams($slug)))->assertRedirect(route('portal.login', portalRouteParams($slug)));
    $this->get(route('portal.account', portalRouteParams($slug)))->assertRedirect(route('portal.login', portalRouteParams($slug)));
});

// ── Middleware: expired session ────────────────────────────────────────────────

it('redirects when portal session is expired', function () {
    $tenant = portalTenant('isp-expired');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id);
    $token = Str::random(64);
    PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => $token,
        'expires_at' => now()->subHour(),
        'last_activity_at' => now()->subHour(),
    ]);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard', portalRouteParams($slug)))
        ->assertRedirect(route('portal.login', portalRouteParams($slug)));
});

it('extends portal session expiry while the customer remains active', function () {
    $tenant = portalTenant('isp-sliding-session');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, ['customer_name' => 'Siti Portal']);
    $token = Str::random(64);

    PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => $token,
        'ip_address' => '127.0.0.1',
        'last_activity_at' => now()->subDays(3),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard', portalRouteParams($slug)));

    $response->assertOk()
        ->assertCookie('portal_session', $token);

    $session = PortalSession::where('token', $token)->first();

    expect($session)->not->toBeNull()
        ->and($session?->expires_at?->gt(now()->addDays(29)))->toBeTrue()
        ->and($session?->last_activity_at?->gt(now()->subMinute()))->toBeTrue();
});

// ── Middleware: cross-tenant session blocked ───────────────────────────────────

it('blocks cross-tenant session access', function () {
    $tenant1 = portalTenant('isp-cross-1');
    $tenant2 = portalTenant('isp-cross-2');
    $slug2 = tenantSlug($tenant2);

    // Session belongs to tenant1's user
    $ppp = makePppUser($tenant1->id);
    $token = makePortalSession($ppp->id);

    // Try to access tenant2's portal with tenant1's session
    $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard', portalRouteParams($slug2)))
        ->assertRedirect(route('portal.login', portalRouteParams($slug2)));
});

// ── Dashboard ─────────────────────────────────────────────────────────────────

it('shows dashboard for authenticated portal user', function () {
    $tenant = portalTenant('isp-dashboard');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, ['customer_name' => 'Budi Santoso']);
    $token = makePortalSession($ppp->id);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard', portalRouteParams($slug)))
        ->assertOk()
        ->assertSee('Budi Santoso');
});

it('re-syncs portal push subscription state when the pwa becomes active again', function () {
    $tenant = portalTenant('isp-push-resync');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, ['customer_name' => 'Push Portal']);
    $token = makePortalSession($ppp->id);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.dashboard', portalRouteParams($slug)))
        ->assertOk()
        ->assertSee('function syncPushSubscriptionState(showInvite)', false)
        ->assertSee("document.addEventListener('visibilitychange'", false)
        ->assertSee("window.addEventListener('focus'", false)
        ->assertSee('serializeSubscription(sub)', false);
});

// ── Invoices ──────────────────────────────────────────────────────────────────

it('shows invoice list for authenticated portal user', function () {
    $tenant = portalTenant('isp-invoices');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id);
    $token = makePortalSession($ppp->id);

    Invoice::create([
        'invoice_number' => 'INV-202501001',
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'customer_name' => $ppp->customer_name,
        'total' => 150000,
        'status' => 'belum_bayar',
        'due_date' => now()->addDays(10),
        'payment_token' => Str::random(32),
    ]);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.invoices', portalRouteParams($slug)))
        ->assertOk()
        ->assertSee('INV-202501001');
});

it('shows old and current invoice badges on the portal invoice list', function () {
    $tenant = portalTenant('isp-invoices-badges');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, [
        'jatuh_tempo' => now()->addDays(10)->toDateString(),
    ]);
    $token = makePortalSession($ppp->id);

    Invoice::create([
        'invoice_number' => 'INV-CTX-OLD-001',
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'customer_name' => $ppp->customer_name,
        'paket_langganan' => 'Paket Lama',
        'total' => 150000,
        'status' => 'unpaid',
        'due_date' => now()->subDays(20),
        'payment_token' => Str::random(32),
    ]);

    Invoice::create([
        'invoice_number' => 'INV-CTX-CUR-001',
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'customer_name' => $ppp->customer_name,
        'paket_langganan' => 'Paket Berjalan',
        'total' => 175000,
        'status' => 'unpaid',
        'due_date' => $ppp->jatuh_tempo,
        'payment_token' => Str::random(32),
    ]);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.invoices', portalRouteParams($slug)))
        ->assertOk()
        ->assertSee('Invoice Tunggakan')
        ->assertSee('Perpanjangan Bulan Berjalan');
});

it('shows arrears badge for stale unpaid invoice from previous month on portal', function () {
    $tenant = portalTenant('isp-invoices-stale-arrears');
    $slug = tenantSlug($tenant);
    $historicalDueDate = now()->subMonthNoOverflow()->day(10)->toDateString();
    $currentDueDate = now()->day(10)->toDateString();

    $ppp = makePppUser($tenant->id, [
        'jatuh_tempo' => $historicalDueDate,
    ]);
    $token = makePortalSession($ppp->id);

    Invoice::create([
        'invoice_number' => 'INV-CTX-STALE-OLD-001',
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'customer_name' => $ppp->customer_name,
        'paket_langganan' => 'Paket Tunggakan Lama',
        'total' => 150000,
        'status' => 'unpaid',
        'due_date' => $historicalDueDate,
        'payment_token' => Str::random(32),
    ]);

    Invoice::create([
        'invoice_number' => 'INV-CTX-STALE-CUR-001',
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'customer_name' => $ppp->customer_name,
        'paket_langganan' => 'Paket Berjalan',
        'total' => 175000,
        'status' => 'unpaid',
        'due_date' => $currentDueDate,
        'payment_token' => Str::random(32),
    ]);

    $this->withCookie('portal_session', $token)
        ->get(route('portal.invoices', portalRouteParams($slug)))
        ->assertOk()
        ->assertSee('INV-CTX-STALE-OLD-001')
        ->assertSee('INV-CTX-STALE-CUR-001')
        ->assertSee('Invoice Tunggakan')
        ->assertSee('Perpanjangan Bulan Berjalan');
});

// ── Change Password ───────────────────────────────────────────────────────────

it('can change portal password', function () {
    $tenant = portalTenant('isp-changepw');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, ['password_clientarea' => 'oldpassword']);
    $token = makePortalSession($ppp->id);

    $this->withCredentials()->withCookie('portal_session', $token)
        ->postJson(route('portal.change-password', portalRouteParams($slug)), [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Hash::check('newpassword123', $ppp->fresh()->password_clientarea))->toBeTrue();
});

it('rejects change password when current password is wrong', function () {
    $tenant = portalTenant('isp-changepw-wrong');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, ['password_clientarea' => 'correctpass']);
    $token = makePortalSession($ppp->id);

    $this->withCredentials()->withCookie('portal_session', $token)
        ->postJson(route('portal.change-password', portalRouteParams($slug)), [
            'current_password' => 'wrongpass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);
});

it('rejects change password when confirmation does not match', function () {
    $tenant = portalTenant('isp-changepw-mismatch');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id, ['password_clientarea' => 'pass']);
    $token = makePortalSession($ppp->id);

    $this->withCredentials()->withCookie('portal_session', $token)
        ->postJson(route('portal.change-password', portalRouteParams($slug)), [
            'current_password' => 'pass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'different',
        ])
        ->assertStatus(422);
});

it('portal customer can update wifi ssid without changing password', function () {
    $tenant = portalTenant('isp-portal-wifi');
    $slug = tenantSlug($tenant);
    TenantSettings::where('user_id', $tenant->id)->update([
        'genieacs_url' => 'http://genieacs.test:7557',
        'genieacs_username' => 'genieacs',
        'genieacs_password' => 'secret',
    ]);

    $ppp = makePppUser($tenant->id, ['customer_name' => 'Pelanggan WiFi']);
    $token = makePortalSession($ppp->id);

    CpeDevice::create([
        'ppp_user_id' => $ppp->id,
        'owner_id' => $tenant->id,
        'genieacs_device_id' => 'PORTAL-WIFI-001',
        'param_profile' => 'igd',
        'cached_params' => [
            'wifi_ssid' => 'Wifi-Lama',
        ],
    ]);

    Http::fake(function ($request) {
        if ($request->method() === 'POST' && str_contains($request->url(), '/devices/PORTAL-WIFI-001/tasks?')) {
            expect($request['parameterValues'])->toHaveCount(1)
                ->and($request['parameterValues'][0][0])->toBe(config('genieacs.params.igd.wifi_ssid'))
                ->and($request['parameterValues'][0][1])->toBe('Wifi-Baru');

            return Http::response(['_id' => 'portal-wifi-task'], 202);
        }

        if ($request->method() === 'DELETE' && str_contains($request->url(), '/tasks/portal-wifi-task')) {
            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    $this->withCredentials()
        ->withCookie('portal_session', $token)
        ->postJson(route('portal.wifi.update', portalRouteParams($slug)), [
            'ssid' => 'Wifi-Baru',
            'password' => '',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Pengaturan WiFi dikirim. Nama WiFi akan berubah saat modem online.',
        ]);

    expect($ppp->fresh()->cpeDevice?->cached_params['wifi_ssid'])->toBe('Wifi-Baru');

});

// ── Logout ────────────────────────────────────────────────────────────────────

it('logout deletes portal session', function () {
    $tenant = portalTenant('isp-logout');
    $slug = tenantSlug($tenant);
    $ppp = makePppUser($tenant->id);
    $token = makePortalSession($ppp->id);

    $this->withCookie('portal_session', $token)
        ->post(route('portal.logout', portalRouteParams($slug)))
        ->assertRedirect(route('portal.login', portalRouteParams($slug)));

    expect(PortalSession::where('token', $token)->exists())->toBeFalse();
});

// ── PortalSession model ────────────────────────────────────────────────────────

it('PortalSession isExpired returns true for expired sessions', function () {
    $tenant = portalTenant('isp-session-model');
    $ppp = makePppUser($tenant->id);

    $expired = PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => Str::random(64),
        'expires_at' => now()->subMinute(),
        'last_activity_at' => now()->subMinute(),
    ]);

    $active = PortalSession::create([
        'ppp_user_id' => $ppp->id,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
        'last_activity_at' => now(),
    ]);

    expect($expired->isExpired())->toBeTrue();
    expect($active->isExpired())->toBeFalse();
});

// ── Multi-tenant: same phone different tenant, login to correct tenant ─────────

it('same phone in two tenants logs into correct tenant via slug', function () {
    $tenant1 = portalTenant('isp-mt-1');
    $tenant2 = portalTenant('isp-mt-2');
    $phone = '6281999888777';
    $slug1 = tenantSlug($tenant1);
    $slug2 = tenantSlug($tenant2);

    $ppp1 = makePppUser($tenant1->id, ['nomor_hp' => $phone, 'password_clientarea' => 'pass1']);
    $ppp2 = makePppUser($tenant2->id, ['nomor_hp' => $phone, 'password_clientarea' => 'pass2']);

    // Login ke tenant1 dengan password tenant1
    $this->post(route('portal.login.post', portalRouteParams($slug1)), [
        'nomor_hp' => $phone,
        'password' => 'pass1',
    ])->assertRedirect(route('portal.dashboard', portalRouteParams($slug1)));

    expect(PortalSession::where('ppp_user_id', $ppp1->id)->exists())->toBeTrue();
    expect(PortalSession::where('ppp_user_id', $ppp2->id)->exists())->toBeFalse();
});
