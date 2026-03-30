<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Mail\TenantRegistered;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\TurnstileService;
use App\Services\WaNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function register(StoreUserRequest $request): RedirectResponse
    {
        $turnstile = new TurnstileService;
        if (! $turnstile->verify($request->input('cf-turnstile-response', ''), $request->ip())) {
            return back()->withErrors(['name' => 'Verifikasi keamanan gagal. Silakan coba lagi.'])->withInput();
        }

        $data = $request->validated();

        // Create user as tenant with trial period
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role' => 'administrator',
            'is_super_admin' => false,
            'subscription_status' => 'trial',
            'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
            'trial_days_remaining' => 14,
            'registered_at' => now(),
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
        ]);

        $subdomain = $data['admin_subdomain'];

        // Create default tenant settings dengan subdomain
        TenantSettings::create([
            'user_id' => $user->id,
            'business_name' => $data['company_name'] ?? $data['name'],
            'business_phone' => $data['phone'] ?? null,
            'business_email' => $data['email'],
            'invoice_prefix' => 'INV',
            'enable_manual_payment' => true,
            'payment_expiry_hours' => 24,
            'auto_isolate_unpaid' => true,
            'grace_period_days' => 3,
            'admin_subdomain' => $subdomain,
            'portal_slug' => $subdomain,
        ]);

        // Kirim email konfirmasi ke tenant (queued, non-blocking)
        try {
            Mail::to($user->email)->queue(new TenantRegistered($user, $data['password']));
        } catch (\Throwable $e) {
            \Log::warning('RegisterController: gagal queue email', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }

        // Kirim notifikasi WA ke super admin via device WA global (queued)
        dispatch(function () use ($user, $subdomain) {
            WaNotificationService::notifyNewTenantRegistered($user, $subdomain);
        })->afterResponse();

        Auth::login($user);

        $dashboardUrl = 'https://'.$subdomain.'.'.config('app.main_domain').'/';

        return redirect()->to($dashboardUrl)->with('status', 'Selamat datang! Anda memiliki 14 hari masa percobaan gratis.');
    }
}
