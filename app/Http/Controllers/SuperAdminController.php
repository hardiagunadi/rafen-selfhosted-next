<?php

namespace App\Http\Controllers;

use App\Mail\TenantInvoiceCreated;
use App\Mail\TenantPaymentConfirmed;
use App\Mail\TenantRegistered;
use App\Models\HotspotUser;
use App\Models\MikrotikConnection;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PppUser;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaMultiSessionDevice;
use App\Models\WaPlatformDeviceRequest;
use App\Services\WaGatewayService;
use App\Services\WaMultiSessionManager;
use App\Services\WaNotificationService;
use App\Services\SelfHostedLicenseViewDataService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;

class SuperAdminController extends Controller
{
    use LogsActivity;

    public function dashboard()
    {
        if ((bool) config('license.self_hosted_enabled', false)) {
            $viewData = app(SelfHostedLicenseViewDataService::class)->forAdminLayout();
            $featureFlags = $viewData['systemFeatureFlags'] ?? [];
            $snapshot = $viewData['systemLicenseSnapshot'] ?? null;

            $stats = [
                'mikrotik_connections' => MikrotikConnection::query()->count(),
                'ppp_users' => PppUser::query()->count(),
                'hotspot_users' => HotspotUser::query()->count(),
                'invoices_unpaid' => \App\Models\Invoice::query()->where('status', 'unpaid')->count(),
                'payments_paid' => Payment::query()->where('status', 'paid')->count(),
                'wa_devices' => WaMultiSessionDevice::query()->count(),
            ];

            $modules = [
                [
                    'title' => 'Router / NAS',
                    'summary' => $stats['mikrotik_connections'].' koneksi terdaftar',
                    'description' => 'Kelola koneksi MikroTik, ping, dan sinkronisasi layanan jaringan.',
                    'route' => route('mikrotik-connections.index'),
                    'label' => 'Buka Router',
                    'enabled' => true,
                    'icon' => 'fas fa-network-wired',
                    'tone' => 'info',
                ],
                [
                    'title' => 'PPP Users',
                    'summary' => $stats['ppp_users'].' pelanggan PPP',
                    'description' => 'Operasional pelanggan PPP, paket, invoice, dan status akun.',
                    'route' => route('ppp-users.index'),
                    'label' => 'Buka PPP Users',
                    'enabled' => true,
                    'icon' => 'fas fa-user-friends',
                    'tone' => 'primary',
                ],
                [
                    'title' => 'Hotspot Users',
                    'summary' => $stats['hotspot_users'].' pelanggan Hotspot',
                    'description' => 'Kelola pelanggan hotspot dan masa aktif voucher / profil.',
                    'route' => route('hotspot-users.index'),
                    'label' => 'Buka Hotspot',
                    'enabled' => true,
                    'icon' => 'fas fa-wifi',
                    'tone' => 'success',
                ],
                [
                    'title' => 'Billing',
                    'summary' => $stats['invoices_unpaid'].' invoice unpaid / '.$stats['payments_paid'].' pembayaran sukses',
                    'description' => 'Pantau invoice, pembayaran, dan tindakan follow-up pelanggan.',
                    'route' => route('invoices.index'),
                    'label' => 'Buka Billing',
                    'enabled' => true,
                    'icon' => 'fas fa-file-invoice-dollar',
                    'tone' => 'warning',
                ],
                [
                    'title' => 'Outage',
                    'summary' => 'Pelacakan gangguan jaringan',
                    'description' => 'Kelola outage, update publik, dan koordinasi tim operasional.',
                    'route' => route('outages.index'),
                    'label' => 'Buka Outage',
                    'enabled' => true,
                    'icon' => 'fas fa-exclamation-triangle',
                    'tone' => 'danger',
                ],
                [
                    'title' => 'WhatsApp',
                    'summary' => $stats['wa_devices'].' device aktif',
                    'description' => 'Kelola gateway, blast, chat, dan notifikasi operasional.',
                    'route' => route('wa-gateway.index'),
                    'label' => 'Buka WhatsApp',
                    'enabled' => (bool) ($featureFlags['wa'] ?? true),
                    'icon' => 'fab fa-whatsapp',
                    'tone' => 'success',
                ],
            ];

            return view('super-admin.dashboard', [
                'selfHostedMode' => true,
                'snapshot' => $snapshot,
                'featureFlags' => $featureFlags,
                'stats' => $stats,
                'modules' => $modules,
            ]);
        }

        $stats = [
            'total_tenants' => User::tenants()->count(),
            'active_subscribers' => User::activeSubscribers()->count(),
            'trial_users' => User::trialUsers()->count(),
            'expired_subscribers' => User::expiredSubscribers()->count(),
            'total_mikrotik' => MikrotikConnection::count(),
            'total_ppp_users' => PppUser::count(),
            'total_revenue' => Payment::paid()->forSubscription()->sum('amount'),
            'monthly_revenue' => Payment::paid()->forSubscription()
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $recentSubscriptions = Subscription::with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentPayments = Payment::with(['user', 'subscription.plan'])
            ->forSubscription()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $expiringSubscriptions = User::activeSubscribers()
            ->where('subscription_expires_at', '<=', now()->addDays(7))
            ->orderBy('subscription_expires_at')
            ->limit(10)
            ->get();

        return view('super-admin.dashboard', compact(
            'stats',
            'recentSubscriptions',
            'recentPayments',
            'expiringSubscriptions'
        ));
    }

    public function tenants(Request $request)
    {
        $query = User::tenants()->with('subscriptionPlan');

        if ($request->filled('status')) {
            $query->where('subscription_status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderByDesc('created_at')->paginate(20);

        return view('super-admin.tenants.index', compact('tenants'));
    }

    public function showTenant(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $tenant->load([
            'subscriptionPlan',
            'subscriptions' => fn ($q) => $q->with('plan')->orderByDesc('created_at')->limit(10),
            'mikrotikConnections',
            'pppUsers',
            'tenantSettings',
        ]);

        $pendingSubscriptions = $tenant->subscriptions()
            ->with('plan')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();
        $tenantRoles = $this->tenantRoleSummaries($tenant);

        $stats = [
            'mikrotik_count' => $tenant->mikrotikConnections()->count(),
            'ppp_users_count' => $tenant->pppUsers()->count(),
            'active_ppp_users' => $tenant->pppUsers()->where('status_akun', 'enable')->count(),
            'hotspot_users_count' => HotspotUser::query()->where('owner_id', $tenant->id)->count(),
            'active_hotspot_users' => HotspotUser::query()
                ->where('owner_id', $tenant->id)
                ->where('status_akun', 'enable')
                ->count(),
            'invoices_count' => $tenant->invoices()->count(),
            'unpaid_invoices' => $tenant->invoices()->unpaid()->count(),
            'total_revenue' => $tenant->invoices()->paid()->sum('total'),
        ];

        $activePppUsers = $stats['active_ppp_users'];
        $activeHotspotUsers = $stats['active_hotspot_users'];
        $activeCustomerCount = $activePppUsers + $activeHotspotUsers;

        return view('super-admin.tenants.show', compact('tenant', 'stats', 'pendingSubscriptions', 'tenantRoles', 'activePppUsers', 'activeHotspotUsers', 'activeCustomerCount'));
    }

    public function editTenant(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('super-admin.tenants.edit', compact('tenant', 'plans'));
    }

    public function updateTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$tenant->id,
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'subscription_status' => 'required|in:trial,active,expired,suspended',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_method' => 'required|in:monthly,license',
            'license_max_mikrotik' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'license_max_ppp_users' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'subscription_expires_at' => 'nullable|date',
            'trial_days_remaining' => 'nullable|integer|min:0',
            'vpn_enabled' => 'boolean',
            'vpn_username' => 'nullable|string|max:100',
            'vpn_password' => 'nullable|string|max:100',
            'vpn_ip' => 'nullable|string|max:45',
        ]);

        if ($validated['subscription_method'] !== User::SUBSCRIPTION_METHOD_LICENSE) {
            $validated['license_max_mikrotik'] = null;
            $validated['license_max_ppp_users'] = null;
        } else {
            $validated['subscription_status'] = 'active';
            $validated['trial_days_remaining'] = 0;
            $validated['subscription_expires_at'] = $validated['subscription_expires_at']
                ?? $this->resolveLicenseExpiryDate($tenant);
        }

        $validated['vpn_enabled'] = $request->boolean('vpn_enabled');

        $tenant->update($validated);

        $this->logActivity('updated', 'Tenant', $tenant->id, $tenant->name.' ('.$tenant->email.')');

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Data tenant berhasil diperbarui.');
    }

    public function activateTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'duration_days' => 'nullable|integer|min:1',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $durationDays = $request->filled('duration_days') ? (int) $request->input('duration_days') : null;
        $duration = $tenant->resolveSubscriptionDurationDays($plan, $durationDays);

        // Expire all previous active/pending subscriptions
        $tenant->subscriptions()->whereIn('status', ['active', 'pending'])->update(['status' => 'expired']);

        $tenant->activateSubscription($plan, $duration);

        // Create subscription record
        Subscription::create([
            'user_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($duration),
            'status' => 'active',
            'amount_paid' => 0,
            'activated_at' => now(),
        ]);

        $this->logActivity('activated', 'Tenant', $tenant->id, $tenant->name.' → paket '.$plan->name);

        return back()->with('success', 'Langganan tenant berhasil diaktifkan.');
    }

    public function suspendTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant->update(['subscription_status' => 'suspended']);

        $this->logActivity('suspended', 'Tenant', $tenant->id, $tenant->name);

        return back()->with('success', 'Tenant berhasil disuspend.');
    }

    public function extendTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'days' => $tenant->isLicenseSubscription()
                ? 'nullable|integer|min:1|max:3650'
                : 'required|integer|min:1|max:365',
        ]);

        $days = $tenant->isLicenseSubscription()
            ? User::LICENSE_DURATION_DAYS
            : (int) $request->input('days');

        $tenant->extendSubscription($days);

        $this->logActivity('extended', 'Tenant', $tenant->id, $tenant->name." (+{$days} hari)");

        if ($tenant->isLicenseSubscription()) {
            return back()->with('success', 'Lisensi tenant diperpanjang 1 tahun (365 hari).');
        }

        return back()->with('success', "Langganan diperpanjang {$days} hari.");
    }

    public function changePlanPreview(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $oldPlan = $tenant->subscriptionPlan;

        $remainingDays = 0;
        $remainingValue = 0;
        $newDurationDays = $tenant->resolveSubscriptionDurationDays($plan);
        $oldDurationDays = $oldPlan ? $tenant->resolveSubscriptionDurationDays($oldPlan) : 0;

        if ($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture() && $oldPlan) {
            $remainingDays = (int) now()->diffInDays($tenant->subscription_expires_at, false);
            $remainingDays = max(0, $remainingDays);
            $pricePerDay = $oldDurationDays > 0 ? ($oldPlan->price / $oldDurationDays) : 0;
            $remainingValue = round($pricePerDay * $remainingDays);
        }

        $proratedCost = max(0, $plan->price - $remainingValue);
        $extraDays = 0;
        if ($remainingValue > $plan->price) {
            $newPricePerDay = $newDurationDays > 0 ? ($plan->price / $newDurationDays) : 1;
            $extraDays = (int) floor(($remainingValue - $plan->price) / $newPricePerDay);
        }
        $totalDuration = $newDurationDays + $extraDays;

        return response()->json([
            'remaining_days' => $remainingDays,
            'remaining_value' => $remainingValue,
            'prorated_cost' => $proratedCost,
            'extra_days' => $extraDays,
            'total_duration' => $totalDuration,
            'new_plan_price' => (float) $plan->price,
            'new_plan_name' => $plan->name,
            'new_plan_days' => $newDurationDays,
        ]);
    }

    public function changePlan(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $oldPlan = $tenant->subscriptionPlan;

        // Calculate prorated values
        $remainingDays = 0;
        $remainingValue = 0;
        $newDurationDays = $tenant->resolveSubscriptionDurationDays($plan);
        $oldDurationDays = $oldPlan ? $tenant->resolveSubscriptionDurationDays($oldPlan) : 0;

        if ($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture() && $oldPlan) {
            $remainingDays = max(0, (int) now()->diffInDays($tenant->subscription_expires_at, false));
            $pricePerDay = $oldDurationDays > 0 ? ($oldPlan->price / $oldDurationDays) : 0;
            $remainingValue = round($pricePerDay * $remainingDays);
        }

        $proratedCost = max(0, $plan->price - $remainingValue);
        $extraDays = 0;
        if ($remainingValue > $plan->price) {
            $newPricePerDay = $newDurationDays > 0 ? ($plan->price / $newDurationDays) : 1;
            $extraDays = (int) floor(($remainingValue - $plan->price) / $newPricePerDay);
        }
        $totalDuration = $newDurationDays + $extraDays;

        $newExpiry = now()->addDays($totalDuration);

        $hasGateway = PaymentGateway::active()->exists();
        $needsPayment = $proratedCost > 0 && $hasGateway;

        // Expire all previous active/pending subscriptions
        $tenant->subscriptions()->whereIn('status', ['active', 'pending'])->update(['status' => 'expired']);

        // Jika gratis/manual → langsung aktifkan. Jika ada tagihan via gateway → tunggu pembayaran dulu
        if (! $needsPayment) {
            $tenant->update([
                'subscription_plan_id' => $plan->id,
                'subscription_status' => 'active',
                'subscription_expires_at' => $newExpiry,
            ]);
        } else {
            // Tandai plan yang akan aktif setelah bayar, tapi jangan ubah status/expires dulu
            $tenant->update(['subscription_plan_id' => $plan->id]);
        }

        // If gateway available and there's a cost → create pending subscription, tenant bayar sendiri
        // If no gateway or cost = 0 → langsung aktifkan (manual / gratis)
        if ($needsPayment) {
            $subscription = Subscription::create([
                'user_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => $newExpiry,
                'status' => 'pending',
                'amount_paid' => $proratedCost,
            ]);
        } else {
            $subscription = Subscription::create([
                'user_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => $newExpiry,
                'status' => 'active',
                'amount_paid' => $proratedCost,
                'activated_at' => now(),
            ]);

            if ($proratedCost > 0) {
                Payment::create([
                    'payment_number' => Payment::generatePaymentNumber(),
                    'payment_type' => 'subscription',
                    'user_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'payment_channel' => 'manual',
                    'payment_method' => $request->input('payment_method', 'Transfer Manual'),
                    'amount' => $proratedCost,
                    'fee' => 0,
                    'total_amount' => $proratedCost,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'notes' => $request->input('notes') ?: "Perubahan paket: {$oldPlan?->name} → {$plan->name}. Sisa nilai: Rp ".number_format($remainingValue, 0, ',', '.'),
                ]);
            }
        }

        // Kirim notifikasi ke tenant
        $paymentToken = $subscription->getOrCreatePaymentToken();
        $paymentUrl = route('subscription.payment.public', $paymentToken);
        $mainDomain = config('app.main_domain', 'rafen.id');
        $subdomain = $tenant->subdomain ?? explode('.', parse_url($tenant->app_url ?? '', PHP_URL_HOST))[0] ?? '';
        $loginUrl = 'https://'.$subdomain.'.'.$mainDomain.'/subscription';

        // Email notifikasi
        try {
            if ($needsPayment) {
                Mail::to($tenant->email)->queue(new TenantInvoiceCreated($tenant, $subscription));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send plan change email', ['error' => $e->getMessage()]);
        }

        // WA notifikasi
        try {
            $phone = $tenant->phone ?? '';
            if ($phone) {
                $waService = WaGatewayService::forSuperAdmin();
                if ($waService) {
                    if ($needsPayment) {
                        $message = "*Tagihan Upgrade Paket*\n\n"
                            ."Halo {$tenant->name},\n\n"
                            ."Paket langganan Anda telah diubah ke *{$plan->name}*.\n\n"
                            ."*Detail Tagihan:*\n"
                            ."Paket: {$plan->name}\n"
                            .'Tagihan prorated: *Rp '.number_format($proratedCost, 0, ',', '.')."*\n"
                            ."Aktif hingga: {$newExpiry->format('d M Y')}\n\n"
                            ."Silakan lakukan pembayaran melalui link berikut:\n"
                            .$paymentUrl."\n\n"
                            .'Akun Anda akan aktif otomatis setelah pembayaran berhasil.';
                    } else {
                        $message = "*Paket Langganan Diperbarui*\n\n"
                            ."Halo {$tenant->name},\n\n"
                            ."Paket langganan Anda telah diubah ke *{$plan->name}*.\n\n"
                            ."Aktif hingga: *{$newExpiry->format('d M Y')}*\n\n"
                            ."Silakan login untuk melanjutkan:\n"
                            .$loginUrl;
                    }
                    $waService->sendMessage($phone, $message, ['event' => 'plan_changed', 'tenant_id' => $tenant->id]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send plan change WA', ['error' => $e->getMessage()]);
        }

        $this->logActivity('plan_changed', 'Tenant', $tenant->id, $tenant->name.": {$oldPlan?->name} → {$plan->name}");

        if ($needsPayment) {
            return back()->with('success', "Paket diubah ke {$plan->name}. Tagihan Rp ".number_format($proratedCost, 0, ',', '.').' telah dikirim ke tenant via WA & email. Subscription aktif setelah pembayaran.');
        }

        return back()->with('success', "Paket berhasil diubah ke {$plan->name}. Tagihan prorated: Rp ".number_format($proratedCost, 0, ',', '.').'. Aktif hingga: '.$newExpiry->format('d M Y').'.');
    }

    public function confirmSubscriptionPayment(Request $request, User $tenant, Subscription $subscription)
    {
        $this->ensureTenantAccount($tenant);

        if ($subscription->user_id !== $tenant->id) {
            abort(403);
        }

        if ($subscription->status !== 'pending') {
            return back()->with('error', 'Langganan ini sudah diproses.');
        }

        $request->validate([
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // Create a manual payment record
        Payment::create([
            'payment_number' => Payment::generatePaymentNumber(),
            'payment_type' => 'subscription',
            'user_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'payment_channel' => 'manual',
            'payment_method' => $request->input('payment_method', 'Transfer Manual'),
            'amount' => $subscription->amount_paid,
            'fee' => 0,
            'total_amount' => $subscription->amount_paid,
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        // Activate subscription
        $subscription->activate();

        // Send payment confirmation email to tenant
        try {
            $payment = Payment::where('subscription_id', $subscription->id)
                ->where('status', 'paid')
                ->latest('paid_at')
                ->first();
            if ($payment) {
                Mail::to($tenant->email)->queue(new TenantPaymentConfirmed($tenant, $subscription->fresh(), $payment));
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to send payment confirmed email', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }

        $this->logActivity('payment_confirmed', 'Subscription', $subscription->id, $tenant->name.' — Rp '.number_format($subscription->amount_paid, 0, ',', '.'));

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi dan langganan telah diaktifkan.');
    }

    public function deleteSubscription(User $tenant, Subscription $subscription)
    {
        $this->ensureTenantAccount($tenant);

        if ($subscription->user_id !== $tenant->id) {
            abort(403);
        }

        if ($subscription->status !== 'pending') {
            return back()->with('error', 'Hanya langganan dengan status pending yang dapat dihapus.');
        }

        // Hapus payment terkait yang masih pending
        $subscription->payments()->where('status', 'pending')->delete();

        $subscription->delete();

        $this->logActivity('subscription_deleted', 'Subscription', $subscription->id, $tenant->name.' — Rp '.number_format($subscription->amount_paid, 0, ',', '.'));

        return back()->with('success', 'Langganan pending dan data pembayaran terkait berhasil dihapus.');
    }

    public function createTenant()
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('super-admin.tenants.create', compact('plans'));
    }

    public function storeTenant(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'admin_subdomain' => [
                'required', 'string', 'max:63', 'regex:/^[a-z0-9\-]+$/',
                'unique:tenant_settings,admin_subdomain',
                'unique:tenant_settings,portal_slug',
            ],
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_method' => 'required|in:monthly,license',
            'license_max_mikrotik' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'license_max_ppp_users' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'trial_days' => 'nullable|integer|min:0|max:90',
        ], [
            'admin_subdomain.required' => 'Subdomain wajib diisi.',
            'admin_subdomain.regex' => 'Subdomain hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'admin_subdomain.unique' => 'Subdomain ini sudah digunakan oleh tenant lain.',
        ]);

        if ($validated['subscription_method'] !== User::SUBSCRIPTION_METHOD_LICENSE) {
            $validated['license_max_mikrotik'] = null;
            $validated['license_max_ppp_users'] = null;
        }
        $isLicenseMethod = $validated['subscription_method'] === User::SUBSCRIPTION_METHOD_LICENSE;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'role' => 'administrator',
            'is_super_admin' => false,
            'subscription_status' => $isLicenseMethod ? 'active' : 'trial',
            'subscription_plan_id' => $validated['subscription_plan_id'] ?? null,
            'subscription_method' => $validated['subscription_method'],
            'license_max_mikrotik' => $validated['license_max_mikrotik'] ?? null,
            'license_max_ppp_users' => $validated['license_max_ppp_users'] ?? null,
            'subscription_expires_at' => $isLicenseMethod ? now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString() : null,
            'trial_days_remaining' => $isLicenseMethod ? 0 : ($validated['trial_days'] ?? 14),
            'registered_at' => now(),
        ]);

        // Create default settings dan set subdomain
        $settings = $user->getSettings();
        $settings->update([
            'business_name' => $validated['company_name'] ?? $validated['name'],
            'business_phone' => $validated['phone'] ?? null,
            'business_email' => $validated['email'],
            'admin_subdomain' => $validated['admin_subdomain'],
            'portal_slug' => $validated['admin_subdomain'],
        ]);

        // Send welcome email with credentials and available plans
        try {
            Mail::to($user->email)->queue(new TenantRegistered($user, $validated['password']));
        } catch (\Throwable $e) {
            \Log::warning('Failed to send tenant registration email', ['tenant_id' => $user->id, 'error' => $e->getMessage()]);
        }

        // Kirim WA selamat datang ke nomor HP tenant (afterResponse, non-blocking)
        $subdomain = $validated['admin_subdomain'];
        dispatch(function () use ($user, $subdomain) {
            WaNotificationService::notifyNewTenantRegistered($user, $subdomain);
        })->afterResponse();

        $this->logActivity('created', 'Tenant', $user->id, $user->name.' ('.$user->email.')');

        $subdomainUrl = 'https://'.$validated['admin_subdomain'].'.'.config('app.main_domain').'/';

        return redirect()->route('super-admin.tenants.show', $user)
            ->with('success', 'Tenant berhasil dibuat.')
            ->with('new_tenant_subdomain_url', $subdomainUrl);
    }

    public function deleteTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $tenantLabel = $tenant->name.' ('.$tenant->email.') [id='.$tenant->id.']';

        \DB::transaction(function () use ($tenant) {
            $ownerId = $tenant->id;

            // Delete all tenant-owned data in dependency order
            \DB::table('cpe_devices')->where('owner_id', $ownerId)->delete();
            \DB::table('ppp_users')->where('owner_id', $ownerId)->delete();
            \DB::table('hotspot_users')->where('owner_id', $ownerId)->delete();
            \DB::table('ppp_profiles')->where('owner_id', $ownerId)->delete();
            \DB::table('hotspot_profiles')->where('owner_id', $ownerId)->delete();
            \DB::table('vouchers')->where('owner_id', $ownerId)->delete();
            \DB::table('invoices')->where('owner_id', $ownerId)->delete();
            \DB::table('transactions')->where('owner_id', $ownerId)->delete();
            \DB::table('payments')->where('user_id', $ownerId)->delete();
            \DB::table('mikrotik_connections')->where('owner_id', $ownerId)->delete();
            \DB::table('olt_connections')->where('owner_id', $ownerId)->delete();
            \DB::table('olt_onu_optics')->where('owner_id', $ownerId)->delete();
            \DB::table('odps')->where('owner_id', $ownerId)->delete();
            \DB::table('bank_accounts')->where('user_id', $ownerId)->delete();
            \DB::table('bandwidth_profiles')->where('owner_id', $ownerId)->delete();
            \DB::table('profile_groups')->where('owner_id', $ownerId)->delete();
            \DB::table('finance_expenses')->where('owner_id', $ownerId)->delete();
            \DB::table('wa_multi_session_devices')->where('user_id', $ownerId)->delete();
            \DB::table('activity_logs')->where('owner_id', $ownerId)->delete();
            \DB::table('login_logs')->where('user_id', $ownerId)->delete();
            \DB::table('subscriptions')->where('user_id', $ownerId)->delete();
            \DB::table('tenant_settings')->where('user_id', $ownerId)->delete();
            // Delete sub-users first, then tenant account
            \DB::table('users')->where('parent_id', $ownerId)->delete();
            $tenant->delete();
        });

        $this->logActivity('deleted', 'Tenant', null, $tenantLabel);

        return redirect()->route('super-admin.tenants')
            ->with('success', 'Tenant dan semua data terkait berhasil dihapus.');
    }

    // System Payment Gateway Settings

    public function paymentGateways()
    {
        $gateways = PaymentGateway::all();

        return view('super-admin.payment-gateways.index', compact('gateways'));
    }

    public function storePaymentGateway(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:payment_gateways,code',
            'provider' => 'required|string|max:50',
            'api_key' => 'nullable|string',
            'private_key' => 'nullable|string',
            'merchant_code' => 'nullable|string|max:50',
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:100',
            'fee_description' => 'nullable|string|max:255',
        ]);

        $validated['is_sandbox'] = $request->boolean('is_sandbox');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['platform_fee_percent'] = $validated['platform_fee_percent'] ?? 0;

        $gw = PaymentGateway::create($validated);

        $this->logActivity('created', 'PaymentGateway', $gw->id, $gw->name.' ('.$gw->provider.')');

        return back()->with('success', 'Payment gateway berhasil ditambahkan.');
    }

    public function updatePaymentGateway(Request $request, PaymentGateway $gateway)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'private_key' => 'nullable|string',
            'merchant_code' => 'nullable|string|max:50',
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:100',
            'fee_description' => 'nullable|string|max:255',
        ]);

        $validated['is_sandbox'] = $request->boolean('is_sandbox');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['platform_fee_percent'] = $validated['platform_fee_percent'] ?? $gateway->platform_fee_percent;

        $gateway->update($validated);

        $this->logActivity('updated', 'PaymentGateway', $gateway->id, $gateway->name);

        return back()->with('success', 'Payment gateway berhasil diperbarui.');
    }

    // Reports

    public function revenueReport(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

        $subscriptionRevenue = Payment::paid()
            ->forSubscription()
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->sum('payments.amount');

        $dailyRevenue = Payment::paid()
            ->forSubscription()
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(payments.paid_at) as date, SUM(payments.amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $revenueByPlan = Payment::paid()
            ->forSubscription()
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name as plan_name, SUM(payments.amount) as total')
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->get();

        return view('super-admin.reports.revenue', compact(
            'startDate',
            'endDate',
            'subscriptionRevenue',
            'dailyRevenue',
            'revenueByPlan'
        ));
    }

    public function tenantsReport(Request $request)
    {
        $tenantsByStatus = User::tenants()
            ->selectRaw('subscription_status, COUNT(*) as total')
            ->groupBy('subscription_status')
            ->get()
            ->pluck('total', 'subscription_status');

        $newTenantsThisMonth = User::tenants()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $churnedThisMonth = User::tenants()
            ->where('subscription_status', 'expired')
            ->whereMonth('subscription_expires_at', now()->month)
            ->whereYear('subscription_expires_at', now()->year)
            ->count();

        $topTenants = User::tenants()
            ->withCount('pppUsers')
            ->orderByDesc('ppp_users_count')
            ->limit(10)
            ->get();

        return view('super-admin.reports.tenants', compact(
            'tenantsByStatus',
            'newTenantsThisMonth',
            'churnedThisMonth',
            'topTenants'
        ));
    }

    // VPN Management for Tenants

    public function vpnSettings(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        return view('super-admin.tenants.vpn', compact('tenant'));
    }

    public function updateVpnSettings(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $validated = $request->validate([
            'vpn_enabled' => 'boolean',
            'vpn_username' => 'required_if:vpn_enabled,true|nullable|string|max:100',
            'vpn_password' => 'nullable|string|max:100',
            'vpn_ip' => 'nullable|ip',
        ]);

        $tenant->update($validated);

        // TODO: Integrate with OpenVPN to actually create/update the VPN user

        return back()->with('success', 'Pengaturan VPN berhasil diperbarui.');
    }

    public function generateVpnCredentials(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $username = 'tenant_'.$tenant->id;
        $password = \Str::random(16);

        $tenant->update([
            'vpn_username' => $username,
            'vpn_password' => $password,
        ]);

        // TODO: Integrate with OpenVPN to create the VPN user

        return back()->with('success', 'Kredensial VPN berhasil dibuat.');
    }

    // ── Server Health ────────────────────────────────────────────────────────

    public function serverHealth()
    {
        $checkService = function (string $unit): string {
            $output = shell_exec('systemctl is-active '.escapeshellarg($unit).' 2>/dev/null');

            return trim((string) $output);
        };

        $services = [
            ['name' => 'Queue Worker', 'unit' => 'rafen-queue', 'status' => $checkService('rafen-queue')],
            ['name' => 'Scheduler Timer', 'unit' => 'rafen-schedule.timer', 'status' => $checkService('rafen-schedule.timer')],
            ['name' => 'FreeRADIUS', 'unit' => 'freeradius', 'status' => $checkService('freeradius')],
            ['name' => 'GenieACS CWMP', 'unit' => 'genieacs-cwmp', 'status' => $checkService('genieacs-cwmp')],
            ['name' => 'GenieACS NBI', 'unit' => 'genieacs-nbi', 'status' => $checkService('genieacs-nbi')],
        ];

        // Disk usage
        $dfOutput = shell_exec('df -P / 2>/dev/null');
        $diskInfo = ['used' => 0, 'total' => 0, 'percent' => 0, 'used_h' => '-', 'total_h' => '-'];
        if ($dfOutput) {
            $lines = array_filter(explode("\n", trim($dfOutput)));
            $dataLine = end($lines);
            if (preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%/', $dataLine, $m)) {
                $total = (int) $m[1];
                $used = (int) $m[2];
                $diskInfo = [
                    'total' => $total,
                    'used' => $used,
                    'percent' => $total > 0 ? round($used / $total * 100) : 0,
                    'used_h' => $this->formatBytes($used * 1024),
                    'total_h' => $this->formatBytes($total * 1024),
                ];
            }
        }

        // Memory usage
        $memOutput = shell_exec('free -b 2>/dev/null');
        $memInfo = ['used' => 0, 'total' => 0, 'percent' => 0, 'used_h' => '-', 'total_h' => '-'];
        if ($memOutput) {
            if (preg_match('/Mem:\s+(\d+)\s+(\d+)/', $memOutput, $m)) {
                $total = (int) $m[1];
                $used = (int) $m[2];
                $memInfo = [
                    'total' => $total,
                    'used' => $used,
                    'percent' => $total > 0 ? round($used / $total * 100) : 0,
                    'used_h' => $this->formatBytes($used),
                    'total_h' => $this->formatBytes($total),
                ];
            }
        }

        // Queue stats from DB
        $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();

        return view('super-admin.server-health', compact(
            'services',
            'diskInfo',
            'memInfo',
            'pendingJobs',
            'failedJobs',
        ));
    }

    public function restartService(string $service)
    {
        $allowed = [
            'rafen-queue' => ['cmd' => 'sudo /bin/systemctl restart rafen-queue',          'label' => 'Queue Worker'],
            'rafen-schedule.timer' => ['cmd' => 'sudo /bin/systemctl restart rafen-schedule.timer', 'label' => 'Scheduler Timer'],
            'freeradius' => ['cmd' => 'sudo /bin/systemctl restart freeradius',           'label' => 'FreeRADIUS'],
            'genieacs-cwmp' => ['cmd' => 'sudo /bin/systemctl restart genieacs-cwmp',        'label' => 'GenieACS CWMP'],
            'genieacs-nbi' => ['cmd' => 'sudo /bin/systemctl restart genieacs-nbi',         'label' => 'GenieACS NBI'],
        ];

        if (! array_key_exists($service, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak dikenal.'], 422);
        }

        $entry = $allowed[$service];
        $output = shell_exec($entry['cmd'].' 2>&1');

        // Small wait then check status
        sleep(1);
        $status = trim((string) shell_exec('systemctl is-active '.escapeshellarg($service).' 2>/dev/null'));
        $ok = $status === 'active';

        $label = $entry['label'];
        $this->logActivity('restarted_service', 'Server', null, $label);

        return response()->json([
            'success' => $ok,
            'status' => $status,
            'message' => $ok ? "{$label} berhasil di-restart." : "Restart {$label} gagal. Status: {$status}.",
        ]);
    }

    public function clearRam()
    {
        // sync dulu agar data di buffer aman, lalu drop page cache (1), dentries & inodes (2)
        shell_exec('sudo /bin/sync 2>&1');
        shell_exec('echo 3 | sudo /usr/bin/tee /proc/sys/vm/drop_caches > /dev/null 2>&1');

        // Read memory setelah clear
        $memOutput = shell_exec('free -b 2>/dev/null');
        $memInfo = ['used_h' => '-', 'total_h' => '-', 'percent' => 0];
        if ($memOutput && preg_match('/Mem:\s+(\d+)\s+(\d+)/', $memOutput, $m)) {
            $total = (int) $m[1];
            $used = (int) $m[2];
            $memInfo = [
                'percent' => $total > 0 ? round($used / $total * 100) : 0,
                'used_h' => $this->formatBytes($used),
                'total_h' => $this->formatBytes($total),
            ];
        }

        $this->logActivity('cleared_ram', 'Server', null, 'Drop page cache (level 3)');

        return response()->json([
            'success' => true,
            'message' => 'Page cache berhasil dibersihkan.',
            'mem' => $memInfo,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    private function ensureTenantAccount(User $tenant): void
    {
        if ($tenant->isSuperAdmin() || $tenant->role !== 'administrator' || $tenant->parent_id !== null) {
            abort(404);
        }
    }

    private function tenantRoleSummaries(User $tenant)
    {
        return User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->where(function ($query) use ($tenant) {
                $query->where('id', $tenant->id)
                    ->orWhere('parent_id', $tenant->id);
            })
            ->groupBy('role')
            ->orderBy('role')
            ->get()
            ->map(function (User $roleSummary): array {
                return [
                    'role' => (string) $roleSummary->role,
                    'label' => $this->roleLabel((string) $roleSummary->role),
                    'total' => (int) $roleSummary->total,
                ];
            })
            ->values();
    }

    private function resolveLicenseExpiryDate(User $tenant): string
    {
        if ($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture()) {
            return $tenant->subscription_expires_at->toDateString();
        }

        return now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString();
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'administrator' => 'Administrator',
            'it_support' => 'IT Support',
            'noc' => 'NOC',
            'keuangan' => 'Keuangan',
            'teknisi' => 'Teknisi',
            'cs' => 'Customer Services',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }

    // ── Email Settings ──────────────────────────────────────────────────────

    public function emailSettings()
    {
        $config = [
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'encryption' => ['smtp' => 'tls', 'smtps' => 'ssl'][config('mail.mailers.smtp.scheme') ?? ''] ?? '',
        ];

        return view('super-admin.settings.email', compact('config'));
    }

    public function updateEmailSettings(Request $request)
    {
        $validated = $request->validate([
            'mailer' => 'required|in:smtp,log,array',
            'host' => 'required_if:mailer,smtp|nullable|string|max:255',
            'port' => 'required_if:mailer,smtp|nullable|integer',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'encryption' => 'nullable|in:tls,ssl,',
        ]);

        $envPath = base_path('.env');
        $env = file_get_contents($envPath);

        $updates = [
            'MAIL_MAILER' => $validated['mailer'],
            'MAIL_HOST' => $validated['host'] ?? '127.0.0.1',
            'MAIL_PORT' => (string) ($validated['port'] ?? 587),
            'MAIL_USERNAME' => $validated['username'] ?? 'null',
            'MAIL_FROM_ADDRESS' => '"'.$validated['from_address'].'"',
            'MAIL_FROM_NAME' => '"'.$validated['from_name'].'"',
        ];

        if (! empty($validated['password'])) {
            $updates['MAIL_PASSWORD'] = $validated['password'];
        }

        if (isset($validated['encryption'])) {
            // Laravel 12: MAIL_SCHEME pakai "smtp" (TLS/STARTTLS) atau "smtps" (SSL), bukan "tls"/"ssl"
            $schemeMap = ['tls' => 'smtp', 'ssl' => 'smtps', '' => 'null'];
            $updates['MAIL_SCHEME'] = $schemeMap[$validated['encryption']] ?? 'null';
        }

        foreach ($updates as $key => $value) {
            // Quote nilai yang mengandung spasi atau karakter khusus agar .env valid
            $needsQuoting = preg_match('/[\s#"\'\\\\]/', $value) && ! (str_starts_with($value, '"') && str_ends_with($value, '"'));
            $envValue = $needsQuoting ? '"'.str_replace('"', '\\"', $value).'"' : $value;
            $env = preg_replace("/^{$key}=.*/m", "{$key}={$envValue}", $env);
        }

        file_put_contents($envPath, $env);

        \Artisan::call('config:clear');

        return back()->with('success', 'Pengaturan email berhasil disimpan.');
    }

    public function testEmailSettings(Request $request)
    {
        $request->validate(['to' => 'required|email']);

        try {
            \Mail::raw(
                'Ini adalah email test dari '.config('app.name').'. Konfigurasi email Anda berfungsi dengan baik.',
                function ($message) use ($request) {
                    $message->to($request->to)
                        ->subject('[Test] Email dari '.config('app.name'));
                }
            );

            return back()->with('success', "Email test berhasil dikirim ke {$request->to}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal kirim email test: '.$e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // WA Gateway Management
    // -------------------------------------------------------------------------

    public function waGateway()
    {
        $packageJsonPath = base_path('wa-multi-session/package.json');
        $packageJson = file_exists($packageJsonPath)
            ? json_decode(file_get_contents($packageJsonPath), true)
            : [];

        $currentBaileys = ltrim((string) ($packageJson['dependencies']['baileys'] ?? '-'), '^~');
        $gatewayVersion = $packageJson['version'] ?? '-';

        $updateInfo = \Cache::get('baileys_update_check');

        // Riwayat upgrade dari cache
        $upgradeHistory = \Cache::get('baileys_upgrade_history', []);

        // Status gateway via WaMultiSessionManager
        $manager = app(WaMultiSessionManager::class);
        $gatewayStatus = $manager->status();

        // Hitung total session aktif di DB
        $totalSessions = \DB::table('wa_multi_session_auth_store')
            ->distinct('session_id')
            ->count('session_id');

        // Semua device yang terdaftar (lintas tenant) beserta info pemiliknya
        $allDevices = WaMultiSessionDevice::with('user:id,name,email')
            ->orderByDesc('is_platform_device')
            ->orderByDesc('is_default')
            ->orderBy('user_id')
            ->get();

        // Live-check status tiap device via gateway (paralel, timeout singkat)
        if ($gatewayStatus['running'] && $allDevices->isNotEmpty()) {
            $token = trim((string) config('wa.multi_session.auth_token', ''));
            $baseUrl = rtrim(config('wa.multi_session.host', '127.0.0.1'), '/');
            $port = (int) config('wa.multi_session.port', 3100);
            $endpoint = "http://{$baseUrl}:{$port}/api/v2/sessions/status";

            $responses = Http::pool(function ($pool) use ($allDevices, $token, $endpoint) {
                return $allDevices->map(fn ($d) => $pool->as($d->session_id)
                    ->timeout(3)
                    ->withToken($token)
                    ->get($endpoint, ['session' => $d->session_id])
                )->all();
            });

            // Simpan live status ke setiap device object (tidak persist ke DB, hanya untuk tampilan)
            foreach ($allDevices as $device) {
                try {
                    $resp = $responses[$device->session_id] ?? null;
                    $status = $resp?->json('data.status', 'unknown') ?? 'unknown';
                    $device->setAttribute('live_status', $status);
                } catch (\Throwable) {
                    $device->setAttribute('live_status', 'unknown');
                }
            }
        }

        // Pending platform device requests
        $pendingDeviceRequests = WaPlatformDeviceRequest::where('status', 'pending')->count();

        return view('super-admin.wa-gateway.index', compact(
            'currentBaileys',
            'gatewayVersion',
            'updateInfo',
            'upgradeHistory',
            'gatewayStatus',
            'totalSessions',
            'allDevices',
            'pendingDeviceRequests',
        ));
    }

    public function waGatewayCheckUpdate()
    {
        \Artisan::call('wa-gateway:check-baileys-update', ['--force' => true]);

        return back()->with('success', 'Pengecekan update baileys selesai.');
    }

    public function waGatewayRestart()
    {
        $manager = app(WaMultiSessionManager::class);
        $result = $manager->restart();

        if ($result['success'] ?? false) {
            $this->logActivity('restarted', 'WaGateway', null, 'wa-multi-session');

            return back()->with('success', 'WA Gateway berhasil di-restart.');
        }

        return back()->with('error', 'Gagal restart WA Gateway: '.($result['message'] ?? 'Unknown error'));
    }

    public function waGatewayUpgrade(Request $request)
    {
        $request->validate([
            'version' => 'required|string|regex:/^[0-9]+\.[0-9]+\.[0-9]+([\-\.][a-zA-Z0-9\.]+)?$/',
        ]);

        $version = $request->input('version');
        $waMsPath = base_path('wa-multi-session');

        // Jalankan npm install
        $installProcess = Process::timeout(120)
            ->path($waMsPath)
            ->run("npm install baileys@{$version} --save 2>&1");

        if (! $installProcess->successful()) {
            return back()->with('error', 'Gagal install baileys@'.$version.': '.substr($installProcess->output(), 0, 300));
        }

        // Restart gateway agar versi baru aktif
        $manager = app(WaMultiSessionManager::class);
        $restart = $manager->restart();

        // Simpan riwayat upgrade
        $history = \Cache::get('baileys_upgrade_history', []);
        array_unshift($history, [
            'version' => $version,
            'upgraded_at' => now()->toDateTimeString(),
            'upgraded_by' => auth()->user()->name,
            'success' => $restart['success'] ?? false,
        ]);
        \Cache::put('baileys_upgrade_history', array_slice($history, 0, 20));

        // Hapus cache cek update agar refresh di halaman
        \Cache::forget('baileys_update_check');

        $this->logActivity('upgraded', 'BaileysPackage', null, "baileys@{$version}");

        if (! ($restart['success'] ?? false)) {
            return back()->with('warning', "Baileys berhasil diupgrade ke {$version} tapi gateway gagal restart. Coba restart manual.");
        }

        return back()->with('success', "Baileys berhasil diupgrade ke versi {$version} dan gateway sudah di-restart.");
    }

    public function togglePlatformDevice(WaMultiSessionDevice $device): JsonResponse
    {
        $device->update(['is_platform_device' => ! $device->is_platform_device]);

        $status = $device->is_platform_device ? 'ditandai sebagai Platform Device' : 'dihapus dari Platform Device';
        $this->logActivity('updated', 'WaMultiSessionDevice', $device->id, "{$device->device_name} — {$status}");

        return response()->json([
            'status' => "Device {$device->device_name} berhasil {$status}.",
            'is_platform_device' => $device->is_platform_device,
        ]);
    }

    // -------------------------------------------------------------------------
    // WA Platform Device Requests
    // -------------------------------------------------------------------------

    public function platformDeviceRequests()
    {
        $requests = WaPlatformDeviceRequest::with(['tenant', 'device', 'approver'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate(25);

        $platformDevices = WaMultiSessionDevice::where('is_platform_device', true)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->get();

        return view('super-admin.wa-platform-device-requests.index', compact('requests', 'platformDevices'));
    }

    public function approvePlatformDeviceRequest(Request $request, WaPlatformDeviceRequest $platformDeviceRequest)
    {
        if (! $platformDeviceRequest->isPending()) {
            return back()->with('error', 'Permintaan ini sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'device_id' => 'required|exists:wa_multi_session_devices,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $device = WaMultiSessionDevice::findOrFail($validated['device_id']);

        if (! $device->is_platform_device) {
            return back()->with('error', 'Device yang dipilih bukan platform device.');
        }

        \DB::transaction(function () use ($platformDeviceRequest, $device, $validated) {
            $platformDeviceRequest->update([
                'status' => 'approved',
                'device_id' => $device->id,
                'notes' => $validated['notes'] ?? null,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            TenantSettings::getOrCreate($platformDeviceRequest->tenant_id)
                ->update(['wa_platform_device_id' => $device->id]);
        });

        $this->logActivity('approved', 'WaPlatformDeviceRequest', $platformDeviceRequest->id,
            ($platformDeviceRequest->tenant->name ?? '-').' → '.$device->device_name);

        return back()->with('success', 'Permintaan disetujui. Tenant sekarang dapat menggunakan platform device.');
    }

    public function rejectPlatformDeviceRequest(Request $request, WaPlatformDeviceRequest $platformDeviceRequest)
    {
        if (! $platformDeviceRequest->isPending()) {
            return back()->with('error', 'Permintaan ini sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $platformDeviceRequest->update([
            'status' => 'rejected',
            'notes' => $validated['notes'] ?? null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        $this->logActivity('rejected', 'WaPlatformDeviceRequest', $platformDeviceRequest->id,
            $platformDeviceRequest->tenant->name ?? '-');

        return back()->with('success', 'Permintaan ditolak.');
    }

    public function revokePlatformDeviceAccess(WaPlatformDeviceRequest $platformDeviceRequest)
    {
        \DB::transaction(function () use ($platformDeviceRequest) {
            TenantSettings::where('user_id', $platformDeviceRequest->tenant_id)
                ->update(['wa_platform_device_id' => null]);

            $platformDeviceRequest->update([
                'status' => 'rejected',
                'notes' => 'Akses dicabut oleh Super Admin.',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);
        });

        $this->logActivity('revoked', 'WaPlatformDeviceRequest', $platformDeviceRequest->id,
            $platformDeviceRequest->tenant->name ?? '-');

        return back()->with('success', 'Akses platform device berhasil dicabut dari tenant.');
    }
}
