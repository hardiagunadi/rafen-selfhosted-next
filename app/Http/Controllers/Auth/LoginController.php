<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\LoginLog;
use App\Models\TenantSettings;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        $request->session()->regenerateToken();

        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);
        $turnstile = new TurnstileService;
        if (! $turnstile->verify($request->input('cf-turnstile-response', ''), $request->ip())) {
            return back()->withErrors(['email' => 'Verifikasi keamanan gagal. Silakan coba lagi.'])->onlyInput('email');
        }

        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            optional($user)->forceFill(['last_login_at' => now()])->save();

            LoginLog::create([
                'user_id' => $user?->id,
                'email' => $credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'event' => 'login',
            ]);

            // Super admin: selalu redirect ke domain utama
            if ($user->isSuperAdmin()) {
                if ($isSelfHostedApp) {
                    return redirect()->intended(route('super-admin.dashboard'))->with('status', 'Berhasil login.');
                }

                $mainDomain = config('app.main_domain', 'rafen.id');
                $currentHost = $request->getHost();
                if ($currentHost !== $mainDomain) {
                    return redirect()->to($request->getScheme().'://'.$mainDomain.'/')->with('status', 'Berhasil login.');
                }

                return redirect()->intended(route('dashboard'))->with('status', 'Berhasil login.');
            }

            if ($isSelfHostedApp) {
                return redirect()->intended(route('dashboard'))->with('status', 'Berhasil login.');
            }

            // Tenant (non-super-admin): pastikan selalu berada di subdomain mereka sendiri
            $currentHost = $request->getHost();
            $mainDomain = config('app.main_domain', 'rafen.id');
            $settings = TenantSettings::where('user_id', $user->effectiveOwnerId())->first();
            $tenantSubdomain = $settings->admin_subdomain ?? null;

            if ($tenantSubdomain) {
                $expectedHost = $tenantSubdomain.'.'.$mainDomain;
                if ($currentHost !== $expectedHost) {
                    // Login dari main domain atau subdomain lain → redirect ke subdomain tenant
                    $tenantUrl = $request->getScheme().'://'.$expectedHost.'/';

                    return redirect()->away($tenantUrl)->with('status', 'Berhasil login.');
                }
            }

            return redirect()->intended(route('dashboard'))->with('status', 'Berhasil login.');
        }

        LoginLog::create([
            'user_id' => null,
            'email' => $credentials['email'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'event' => 'failed',
        ]);

        return back()->withErrors([
            'email' => 'Kredensial tidak valid.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        LoginLog::create([
            'user_id' => $user?->id,
            'email' => $user?->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'event' => 'logout',
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Berhasil logout.');
    }
}
