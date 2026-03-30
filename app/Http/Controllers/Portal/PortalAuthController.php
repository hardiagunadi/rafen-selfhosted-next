<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalSession;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\PwaIconService;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortalAuthController extends Controller
{
    /**
     * Resolve TenantSettings dari subdomain.
     * Jika tidak ada subdomain (main domain), coba via ?slug= query param.
     * Returns null jika perlu redirect ke subdomain.
     */
    private function resolveTenant(): TenantSettings
    {
        $subdomain = app()->has('tenant_subdomain') ? app('tenant_subdomain') : null;

        if ($subdomain) {
            $settings = TenantSettings::where('admin_subdomain', $subdomain)
                ->orWhere('portal_slug', $subdomain)
                ->first();

            abort_unless($settings, 404, 'Portal tidak ditemukan.');

            return $settings;
        }

        // Akses dari main domain — coba ?slug= query param
        $slug = request()->query('slug');
        if ($slug) {
            $settings = TenantSettings::where('portal_slug', $slug)
                ->orWhere('admin_subdomain', $slug)
                ->first();

            abort_unless($settings, 404, 'Portal tidak ditemukan.');

            return $settings;
        }

        abort(404, 'Portal tidak ditemukan. Gunakan URL subdomain tenant Anda.');
    }

    /**
     * Jika tenant punya admin_subdomain dan request bukan dari subdomain tsb, redirect ke sana.
     */
    private function redirectToSubdomainIfNeeded(TenantSettings $settings): ?RedirectResponse
    {
        if (! $settings->admin_subdomain) {
            return null;
        }

        $mainDomain = config('app.main_domain', 'rafen.id');
        $expectedHost = $settings->admin_subdomain.'.'.$mainDomain;
        $currentHost = request()->getHost();

        if ($currentHost !== $expectedHost) {
            $portalUrl = request()->getScheme().'://'.$expectedHost.'/portal/login';

            return redirect()->away($portalUrl);
        }

        return null;
    }

    /**
     * Build portal URL yang mempertahankan subdomain saat ini.
     * route() menggunakan APP_URL (rafen.id), sehingga redirect ke main domain —
     * harus diganti dengan URL berbasis request->getSchemeAndHttpHost().
     */
    private function portalUrl(string $routeName, ?TenantSettings $settings = null): string
    {
        $path = match ($routeName) {
            'portal.login' => '/portal/login',
            'portal.dashboard' => '/portal',
            default => '/portal',
        };

        return request()->getSchemeAndHttpHost().$path.$this->portalQueryString($settings);
    }

    private function portalQueryString(?TenantSettings $settings = null): string
    {
        if (app()->has('tenant_subdomain') && app('tenant_subdomain')) {
            return '';
        }

        $slug = request()->query('slug');
        if (! $slug && $settings && ! empty($settings->portal_slug)) {
            $slug = $settings->portal_slug;
        }

        if (! $slug) {
            return '';
        }

        return '?'.http_build_query(['slug' => $slug]);
    }

    public function manifest(PwaIconService $pwaIconService): Response
    {
        $settings = $this->resolveTenant();
        $startUrl = '/portal/';
        $appName = $pwaIconService->appName($settings, 'Portal Pelanggan', 'Portal');
        $shortName = $pwaIconService->appShortName($settings, 'Portal Pelanggan', 'Portal');

        $icons = [
            ['src' => $pwaIconService->iconUrl($settings, 192, 'portal.icon', $this->portalIconRouteParameters($settings)), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => $pwaIconService->iconUrl($settings, 512, 'portal.icon', $this->portalIconRouteParameters($settings)), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ];

        $data = json_encode([
            'id' => '/portal/',
            'name' => $appName,
            'short_name' => $shortName,
            'description' => 'Portal Pelanggan — Cek tagihan & status layanan internet',
            'start_url' => $startUrl,
            'scope' => '/portal/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#0a3e68',
            'theme_color' => '#0f6b95',
            'lang' => 'id',
            'icons' => $icons,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($data, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-store',
        ])->withoutCookie('XSRF-TOKEN')->withoutCookie('laravel-session');
    }

    public function icon(int $size, PwaIconService $pwaIconService): BinaryFileResponse
    {
        abort_unless(in_array($size, [32, 180, 192, 512], true), 404);

        return response()->file($pwaIconService->iconPath($this->resolveTenant(), $size), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function showLogin(Request $request): Response|RedirectResponse
    {
        $tenantSettings = $this->resolveTenant();

        if ($redirect = $this->redirectToSubdomainIfNeeded($tenantSettings)) {
            return $redirect;
        }

        $request->session()->regenerateToken();

        return response()
            ->view('portal.login', compact('tenantSettings'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * @return array<string, string|int>
     */
    private function portalIconRouteParameters(TenantSettings $settings): array
    {
        $parameters = [];

        if (! app()->has('tenant_subdomain') || ! app('tenant_subdomain')) {
            $slug = request()->query('slug') ?: $settings->portal_slug ?: $settings->admin_subdomain;
            if ($slug) {
                $parameters['slug'] = $slug;
            }
        }

        return $parameters;
    }

    public function login(Request $request)
    {
        $tenantSettings = $this->resolveTenant();

        if ($redirect = $this->redirectToSubdomainIfNeeded($tenantSettings)) {
            return $redirect;
        }

        $turnstile = new TurnstileService;
        if (! $turnstile->verify($request->input('cf-turnstile-response', ''), $request->ip())) {
            return back()->withErrors(['nomor_hp' => 'Verifikasi keamanan gagal. Silakan coba lagi.'])->withInput();
        }

        $request->validate([
            'nomor_hp' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $phone = $this->normalizePhone($request->nomor_hp);
        $ownerId = $tenantSettings->user_id;

        $pppUsers = PppUser::where('nomor_hp', $phone)
            ->where('owner_id', $ownerId)
            ->whereNotNull('password_clientarea')
            ->get();

        if ($pppUsers->isEmpty()) {
            return back()->withErrors(['nomor_hp' => 'Nomor HP tidak ditemukan atau password belum diatur oleh admin.'])->withInput();
        }

        // Find the user whose password matches
        $matchedUser = null;
        foreach ($pppUsers as $pppUser) {
            $storedPassword = $pppUser->password_clientarea;

            $matched = false;
            try {
                $matched = Hash::check($request->password, $storedPassword);
            } catch (\Throwable) {
            }
            if (! $matched) {
                $matched = $storedPassword === $request->password;
            }
            if ($matched) {
                $matchedUser = $pppUser;
                break;
            }
        }

        if (! $matchedUser) {
            return back()->withErrors(['password' => 'Password salah.'])->withInput();
        }

        // Create portal session
        $token = Str::random(64);
        PortalSession::create([
            'ppp_user_id' => $matchedUser->id,
            'token' => $token,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 255),
            'last_activity_at' => now(),
            'expires_at' => PortalSession::newExpiry(),
        ]);

        $cookie = Cookie::make('portal_session', $token, PortalSession::LIFETIME_MINUTES, '/', null, false, true);

        return redirect()->to($this->portalUrl('portal.dashboard', $tenantSettings))->withCookie($cookie);
    }

    public function logout(Request $request)
    {
        $token = $request->cookies->get('portal_session');
        if ($token) {
            PortalSession::where('token', $token)->delete();
        }

        $cookie = Cookie::forget('portal_session');

        $tenantSettings = null;
        $slug = $request->query('slug');
        if ($slug) {
            $tenantSettings = TenantSettings::where('portal_slug', $slug)
                ->orWhere('admin_subdomain', $slug)
                ->first();
        }

        return redirect()->to($this->portalUrl('portal.login', $tenantSettings))->withCookie($cookie);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
}
