<?php

namespace App\Http\Controllers;

use App\Http\Requests\FetchYCloudPhoneNumbersRequest;
use App\Http\Requests\TestMetaWhatsAppRequest;
use App\Http\Requests\TestYCloudWhatsAppRequest;
use App\Http\Requests\UpdateTenantMapCacheRequest;
use App\Http\Requests\UpdateTenantModuleSettingsRequest;
use App\Models\BankAccount;
use App\Models\PaymentGateway;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaBlastLog;
use App\Models\WaMultiSessionDevice;
use App\Models\WaPlatformDeviceRequest;
use App\Services\DuitkuService;
use App\Services\MetaWhatsAppCloudApiService;
use App\Services\MidtransService;
use App\Services\TripayService;
use App\Services\WaGatewayService;
use App\Services\WaMultiSessionManager;
use App\Services\YCloudWhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantSettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);
        $tenants = null;
        $selectedTenant = null;

        if ($user->isSuperAdmin() && ! $isSelfHostedApp) {
            $tenants = User::query()->tenants()->orderBy('name')->get();
            $tenantId = $request->integer('tenant_id');
            if ($tenantId > 0) {
                $selectedTenant = $tenants->firstWhere('id', $tenantId);
            }
            $settings = $selectedTenant
                ? TenantSettings::getOrCreate($selectedTenant->id)
                : null;
            $bankAccounts = $selectedTenant
                ? $selectedTenant->bankAccounts()->orderBy('is_primary', 'desc')->get()
                : collect();
        } else {
            $settings = $user->getSettings();
            $bankAccounts = $user->bankAccounts()->orderBy('is_primary', 'desc')->get();
        }

        $platformGateways = PaymentGateway::active()->get();

        return view('tenant-settings.index', compact('settings', 'bankAccounts', 'platformGateways', 'tenants', 'selectedTenant', 'isSelfHostedApp'));
    }

    public function updateBusiness(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $user = $request->user();

        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
            'business_address' => 'nullable|string|max:1000',
            'npwp' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'portal_slug' => [
                'nullable', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('tenant_settings', 'portal_slug')
                    ->ignore($user->getSettings()?->id),
            ],
            'invoice_prefix' => 'nullable|string|max:10',
            'invoice_footer' => 'nullable|string|max:1000',
            'invoice_notes' => 'nullable|string|max:1000',
            'billing_date' => 'nullable|integer|min:1|max:28',
        ], [
            'portal_slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung (-).',
            'portal_slug.unique' => 'Slug ini sudah digunakan oleh tenant lain.',
        ]);

        $settings = $user->getSettings();
        $settings->update($validated);

        // Sinkronisasi admin_subdomain = portal_slug agar portal bisa diakses via subdomain
        if (isset($validated['portal_slug'])) {
            $settings->update(['admin_subdomain' => $validated['portal_slug']]);
        }

        return back()->with('success', 'Pengaturan bisnis berhasil diperbarui.');
    }

    public function updatePayment(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);

        $validated = $request->validate([
            'enable_qris_payment' => 'boolean',
            'enable_va_payment' => 'boolean',
            'enable_manual_payment' => 'boolean',
            'active_gateway' => 'nullable|string|in:tripay,midtrans,duitku,ipaymu,xendit',
            // Tripay
            'tripay_api_key' => 'nullable|string|max:255',
            'tripay_private_key' => 'nullable|string|max:255',
            'tripay_merchant_code' => 'nullable|string|max:50',
            'tripay_sandbox' => 'boolean',
            // Midtrans
            'midtrans_server_key' => 'nullable|string|max:255',
            'midtrans_client_key' => 'nullable|string|max:255',
            'midtrans_merchant_id' => 'nullable|string|max:50',
            'midtrans_sandbox' => 'boolean',
            // Duitku
            'duitku_merchant_code' => 'nullable|string|max:50',
            'duitku_api_key' => 'nullable|string|max:255',
            'duitku_sandbox' => 'boolean',
            // iPaymu
            'ipaymu_va' => 'nullable|string|max:50',
            'ipaymu_api_key' => 'nullable|string|max:255',
            'ipaymu_sandbox' => 'boolean',
            // Xendit
            'xendit_secret_key' => 'nullable|string|max:255',
            'xendit_webhook_token' => 'nullable|string|max:255',
            'xendit_sandbox' => 'boolean',
            // Common
            'enabled_payment_channels' => 'nullable|array',
            'payment_expiry_hours' => 'integer|min:1|max:168',
            'auto_isolate_unpaid' => 'boolean',
            'grace_period_days' => 'integer|min:0|max:30',
            // Platform Gateway
            'use_platform_gateway' => 'boolean',
            'platform_payment_gateway_id' => 'nullable|exists:payment_gateways,id',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();

        // Checkbox tidak dikirim browser saat unchecked — paksa false jika tidak ada di request
        foreach (['enable_qris_payment', 'enable_va_payment', 'enable_manual_payment', 'tripay_sandbox', 'midtrans_sandbox', 'duitku_sandbox', 'ipaymu_sandbox', 'xendit_sandbox', 'auto_isolate_unpaid', 'use_platform_gateway'] as $boolField) {
            if (! array_key_exists($boolField, $validated)) {
                $validated[$boolField] = false;
            }
        }

        if ($isSelfHostedApp) {
            $validated['use_platform_gateway'] = false;
            $validated['platform_payment_gateway_id'] = null;
        }

        // Jika use_platform_gateway = false, clear platform_payment_gateway_id
        if (! $validated['use_platform_gateway']) {
            $validated['platform_payment_gateway_id'] = null;
        }

        $settings->update($validated);

        return back()->with('success', 'Pengaturan pembayaran berhasil diperbarui.');
    }

    public function updateModules(UpdateTenantModuleSettingsRequest $request)
    {
        $settings = $request->user()->getSettings();
        $settings->update($request->validated());

        return back()->with('success', 'Pengaturan modul tenant berhasil diperbarui.');
    }

    public function updateMapCache(UpdateTenantMapCacheRequest $request)
    {
        $settings = $request->user()->getSettings();
        $validated = $request->validated();

        $mapCacheEnabled = filter_var($request->input('map_cache_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $centerLatitude = $validated['map_cache_center_lat'] ?? null;
        $centerLongitude = $validated['map_cache_center_lng'] ?? null;
        $coverageRadiusKm = $validated['map_cache_radius_km'] ?? null;
        $minZoom = $validated['map_cache_min_zoom'] ?? null;
        $maxZoom = $validated['map_cache_max_zoom'] ?? null;

        $newConfig = [
            'map_cache_enabled' => $mapCacheEnabled,
            'map_cache_center_lat' => $centerLatitude !== null ? round((float) $centerLatitude, 7) : null,
            'map_cache_center_lng' => $centerLongitude !== null ? round((float) $centerLongitude, 7) : null,
            'map_cache_radius_km' => $coverageRadiusKm !== null ? round((float) $coverageRadiusKm, 2) : (float) ($settings->map_cache_radius_km ?? 3),
            'map_cache_min_zoom' => $minZoom ?? (int) ($settings->map_cache_min_zoom ?? 14),
            'map_cache_max_zoom' => $maxZoom ?? (int) ($settings->map_cache_max_zoom ?? 17),
        ];

        $previousConfig = [
            'map_cache_enabled' => (bool) $settings->map_cache_enabled,
            'map_cache_center_lat' => $settings->map_cache_center_lat !== null ? round((float) $settings->map_cache_center_lat, 7) : null,
            'map_cache_center_lng' => $settings->map_cache_center_lng !== null ? round((float) $settings->map_cache_center_lng, 7) : null,
            'map_cache_radius_km' => $settings->map_cache_radius_km !== null ? round((float) $settings->map_cache_radius_km, 2) : 3.0,
            'map_cache_min_zoom' => (int) ($settings->map_cache_min_zoom ?? 14),
            'map_cache_max_zoom' => (int) ($settings->map_cache_max_zoom ?? 17),
        ];

        if ($previousConfig !== $newConfig) {
            $newConfig['map_cache_version'] = max(1, (int) ($settings->map_cache_version ?? 1)) + 1;
        }

        $settings->update($newConfig);

        return back()->with('success', 'Pengaturan cache peta coverage berhasil diperbarui.');
    }

    public function testTripay(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasTripayConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial Tripay belum dikonfigurasi.',
            ]);
        }

        $tripay = TripayService::forTenant($settings);
        $channels = $tripay->getPaymentChannels();

        if (empty($channels)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Tripay. Periksa kredensial Anda.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Koneksi Tripay berhasil!',
            'channels' => $channels,
        ]);
    }

    public function testMidtrans(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasMidtransConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial Midtrans belum dikonfigurasi.',
            ]);
        }

        $midtrans = MidtransService::forTenant($settings);
        $channels = $midtrans->getPaymentChannels();

        return response()->json([
            'success' => true,
            'message' => 'Koneksi Midtrans berhasil!',
            'channels' => $channels,
        ]);
    }

    public function testDuitku(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasDuitkuConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial Duitku belum dikonfigurasi.',
            ]);
        }

        $duitku = DuitkuService::forTenant($settings);
        $channels = $duitku->getPaymentChannels();

        return response()->json([
            'success' => true,
            'message' => 'Koneksi Duitku berhasil! '.count($channels).' channel tersedia.',
            'channels' => $channels,
        ]);
    }

    public function getPaymentChannels(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasTripayConfigured()) {
            return response()->json([
                'success' => false,
                'channels' => [],
            ]);
        }

        $tripay = TripayService::forTenant($settings);
        $channels = $tripay->getPaymentChannels();

        // Group channels
        $groupedChannels = [];
        foreach (TripayService::getChannelGroups() as $key => $group) {
            $groupChannels = array_filter($channels, fn ($ch) => in_array($ch['code'], $group['codes']));
            if (! empty($groupChannels)) {
                $groupedChannels[$key] = [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'channels' => array_values($groupChannels),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'channels' => $channels,
            'grouped' => $groupedChannels,
        ]);
    }

    // Bank Account Management

    public function storeBankAccount(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        $user = $request->user();

        $bankAccount = $user->bankAccounts()->create($validated);

        if ($validated['is_primary'] ?? false) {
            $bankAccount->setAsPrimary();
        }

        return back()->with('success', 'Rekening bank berhasil ditambahkan.');
    }

    public function updateBankAccount(Request $request, BankAccount $bankAccount)
    {
        $user = $request->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $bankAccount->update($validated);

        if ($validated['is_primary'] ?? false) {
            $bankAccount->setAsPrimary();
        }

        return back()->with('success', 'Rekening bank berhasil diperbarui.');
    }

    public function destroyBankAccount(BankAccount $bankAccount)
    {
        $user = auth()->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $bankAccount->delete();

        return back()->with('success', 'Rekening bank berhasil dihapus.');
    }

    public function setPrimaryBankAccount(BankAccount $bankAccount)
    {
        $user = auth()->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $bankAccount->setAsPrimary();

        return back()->with('success', 'Rekening utama berhasil diubah.');
    }

    public function waGateway(Request $request, WaMultiSessionManager $manager)
    {
        $user = $request->user();
        $isSelfHostedApp = (bool) config('license.self_hosted_enabled', false);

        $tenants = null;
        $selectedTenant = null;

        if ($user->isSuperAdmin() && ! $isSelfHostedApp) {
            $tenants = User::query()
                ->tenants()
                ->orderBy('name')
                ->get();

            $tenantId = $request->integer('tenant_id');
            if ($tenantId) {
                $selectedTenant = $tenants->firstWhere('id', $tenantId);
            }

            $settings = $selectedTenant
                ? TenantSettings::getOrCreate($selectedTenant->id)
                : null;
        } else {
            $settings = $user->getSettings();
        }

        if ($settings) {
            $this->ensureLocalWaGatewayParameters($settings);
            $settings->loadMissing('waPlatformDevice');
        }

        $waServiceStatus = null;

        // Cek apakah tenant punya pending request untuk platform device
        $pendingPlatformRequest = null;
        if (! $user->isSuperAdmin() && $settings && ! $settings->hasWaPlatformDevice()) {
            $pendingPlatformRequest = WaPlatformDeviceRequest::where('tenant_id', $settings->user_id)
                ->where('status', 'pending')
                ->first();
        }

        if ($isSelfHostedApp) {
            $pendingPlatformRequest = null;
        }

        if ($user->isSuperAdmin()) {
            $waServiceStatus = [
                'success' => true,
                'message' => 'Status service berhasil diambil.',
                'data' => $manager->status(),
            ];
        }

        return view('wa-gateway.index', compact('settings', 'tenants', 'selectedTenant', 'waServiceStatus', 'pendingPlatformRequest', 'isSelfHostedApp'));
    }

    public function storePlatformDeviceRequest(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser() || $user->isSuperAdmin()) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();
        $settings = $user->getSettings();

        if ($settings && $settings->hasWaPlatformDevice()) {
            return response()->json(['success' => false, 'message' => 'Tenant sudah menggunakan platform device.'], 422);
        }

        $existing = WaPlatformDeviceRequest::where('tenant_id', $ownerId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki permintaan yang sedang diproses.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        WaPlatformDeviceRequest::create([
            'tenant_id' => $ownerId,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Permintaan berhasil dikirim. Tunggu persetujuan Super Admin.']);
    }

    public function cancelPlatformDeviceRequest(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser() || $user->isSuperAdmin()) {
            abort(403);
        }

        WaPlatformDeviceRequest::where('tenant_id', $user->effectiveOwnerId())
            ->where('status', 'pending')
            ->delete();

        return response()->json(['success' => true, 'message' => 'Permintaan berhasil dibatalkan.']);
    }

    public function updateWa(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'wa_provider' => 'nullable|string|in:local,ycloud',
            'wa_cost_strategy' => 'nullable|string|in:optimize_free_window,always_paid,always_free_window',
            'wa_notify_registration' => 'boolean',
            'wa_notify_invoice' => 'boolean',
            'wa_notify_payment' => 'boolean',
            'wa_broadcast_enabled' => 'boolean',
            'wa_blast_multi_device' => 'boolean',
            'wa_blast_message_variation' => 'boolean',
            'wa_blast_delay_min_ms' => 'numeric|min:2|max:15',
            'wa_blast_delay_max_ms' => 'numeric|min:2|max:20',
            'wa_antispam_enabled' => 'boolean',
            'wa_antispam_delay_ms' => 'numeric|min:0.5|max:10',
            'wa_antispam_max_per_minute' => 'integer|min:1|max:20',
            'wa_msg_randomize' => 'boolean',
            'ycloud_enabled' => 'boolean',
            'ycloud_api_key' => 'nullable|string|max:10000',
            'ycloud_waba_id' => 'nullable|string|max:100',
            'ycloud_phone_number_id' => 'nullable|string|max:100',
            'ycloud_business_number' => 'nullable|string|max:30',
            'ycloud_webhook_secret' => 'nullable|string|max:255',
            'ycloud_allow_group_fallback_local' => 'boolean',
            'wa_template_registration' => 'nullable|string|max:10000',
            'wa_template_invoice' => 'nullable|string|max:10000',
            'wa_template_payment' => 'nullable|string|max:10000',
            'wa_notify_on_process' => 'boolean',
            'wa_template_on_process' => 'nullable|string|max:10000',
            'tenant_id' => 'nullable|integer',
        ]);

        $waBooleanFields = [
            'wa_notify_registration',
            'wa_notify_invoice',
            'wa_notify_payment',
            'wa_broadcast_enabled',
            'wa_blast_multi_device',
            'wa_blast_message_variation',
            'wa_antispam_enabled',
            'wa_msg_randomize',
            'wa_notify_on_process',
            'ycloud_enabled',
            'ycloud_allow_group_fallback_local',
        ];
        foreach ($waBooleanFields as $field) {
            $validated[$field] = $request->boolean($field);
        }

        if ($user->isSuperAdmin() && ! empty($validated['tenant_id'])) {
            $tenant = User::query()
                ->tenants()
                ->where('id', $validated['tenant_id'])
                ->firstOrFail();
            $settings = TenantSettings::getOrCreate($tenant->id);
        } else {
            $settings = $user->getSettings();
        }

        unset($validated['tenant_id']);
        if (array_key_exists('wa_antispam_delay_ms', $validated) && $validated['wa_antispam_delay_ms'] !== null) {
            $validated['wa_antispam_delay_ms'] = $this->convertSecondsToMilliseconds((float) $validated['wa_antispam_delay_ms']);
        }

        if (array_key_exists('wa_blast_delay_min_ms', $validated) && $validated['wa_blast_delay_min_ms'] !== null) {
            $validated['wa_blast_delay_min_ms'] = $this->convertSecondsToMilliseconds((float) $validated['wa_blast_delay_min_ms']);
        }

        if (array_key_exists('wa_blast_delay_max_ms', $validated) && $validated['wa_blast_delay_max_ms'] !== null) {
            $validated['wa_blast_delay_max_ms'] = $this->convertSecondsToMilliseconds((float) $validated['wa_blast_delay_max_ms']);
        }

        if (! empty($validated['wa_blast_delay_min_ms']) && ! empty($validated['wa_blast_delay_max_ms'])) {
            $validated['wa_blast_delay_max_ms'] = max(
                (int) $validated['wa_blast_delay_min_ms'],
                (int) $validated['wa_blast_delay_max_ms']
            );
        }

        $gatewayUrl = trim((string) config('wa.multi_session.public_url', ''));
        $configuredToken = trim((string) config('wa.multi_session.auth_token', ''));
        $configuredKey = trim((string) config('wa.multi_session.master_key', ''));

        $validated['wa_gateway_url'] = $gatewayUrl;
        $validated['wa_gateway_token'] = $configuredToken !== '' ? $configuredToken : (string) ($settings->wa_gateway_token ?? '');
        $validated['wa_gateway_key'] = $configuredKey !== '' ? $configuredKey : (string) ($settings->wa_gateway_key ?? '');
        $validated['wa_webhook_secret'] = trim((string) ($settings->wa_webhook_secret ?? '')) !== ''
            ? $settings->wa_webhook_secret
            : 'tenant-'.$settings->user_id;
        $validated['wa_provider'] = (string) ($validated['wa_provider'] ?? ($settings->wa_provider ?? 'local'));
        $validated['wa_cost_strategy'] = (string) ($validated['wa_cost_strategy'] ?? ($settings->wa_cost_strategy ?? 'optimize_free_window'));
        $validated['ycloud_webhook_secret'] = trim((string) ($validated['ycloud_webhook_secret'] ?? '')) !== ''
            ? trim((string) ($validated['ycloud_webhook_secret'] ?? ''))
            : ((string) ($settings->ycloud_webhook_secret ?? '') !== '' ? (string) $settings->ycloud_webhook_secret : 'ycloud-tenant-'.$settings->user_id);
        $validated = $this->applySafeLocalWaSettings($validated, $settings);

        $settings->update($validated);

        return back()->with('success', 'Pengaturan WhatsApp berhasil diperbarui.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applySafeLocalWaSettings(array $validated, TenantSettings $settings): array
    {
        if (($validated['wa_provider'] ?? $settings->wa_provider ?? 'local') === 'ycloud') {
            return $validated;
        }

        $validated['wa_antispam_delay_ms'] = max(
            TenantSettings::SAFE_WA_ANTISPAM_DELAY_MS,
            (int) ($validated['wa_antispam_delay_ms'] ?? $settings->wa_antispam_delay_ms ?? TenantSettings::SAFE_WA_ANTISPAM_DELAY_MS)
        );
        $validated['wa_antispam_max_per_minute'] = max(
            1,
            min(
                TenantSettings::SAFE_WA_ANTISPAM_MAX_PER_MINUTE,
                (int) ($validated['wa_antispam_max_per_minute'] ?? $settings->wa_antispam_max_per_minute ?? TenantSettings::SAFE_WA_ANTISPAM_MAX_PER_MINUTE)
            )
        );
        $validated['wa_blast_delay_min_ms'] = max(
            TenantSettings::SAFE_WA_BLAST_DELAY_MIN_MS,
            (int) ($validated['wa_blast_delay_min_ms'] ?? $settings->wa_blast_delay_min_ms ?? TenantSettings::SAFE_WA_BLAST_DELAY_MIN_MS)
        );
        $validated['wa_blast_delay_max_ms'] = max(
            TenantSettings::SAFE_WA_BLAST_DELAY_MAX_MS,
            (int) ($validated['wa_blast_delay_max_ms'] ?? $settings->wa_blast_delay_max_ms ?? TenantSettings::SAFE_WA_BLAST_DELAY_MAX_MS),
            (int) $validated['wa_blast_delay_min_ms']
        );

        return $validated;
    }

    private function convertSecondsToMilliseconds(float $seconds): int
    {
        return max(0, (int) round($seconds * 1000));
    }

    public function testWa(Request $request)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
        ]);
        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi wa-multi-session belum lengkap. Periksa WA_MULTI_SESSION_PUBLIC_URL dan WA_MULTI_SESSION_AUTH_TOKEN.',
            ], 422);
        }

        $result = $service->testConnection();

        \Log::info('WA testConnection result', $result);

        return response()->json([
            'success' => $result['status'],
            'message' => $result['message'],
            'http_status' => $result['http_status'] ?? null,
            'network_error' => $result['network_error'] ?? false,
            'gateway_response' => $result['data'] ?? null,
        ]);
    }

    public function testWaMeta(TestMetaWhatsAppRequest $request, MetaWhatsAppCloudApiService $service)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        if (! $service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Meta WhatsApp Cloud API belum dikonfigurasi. Isi META_WHATSAPP_ACCESS_TOKEN dan META_WHATSAPP_PHONE_NUMBER_ID.',
            ], 422);
        }

        $targetPhone = trim((string) ($request->validated('phone') ?? ''));
        if ($targetPhone === '') {
            $targetPhone = trim((string) ($settings->business_phone ?? ''));
        }

        if ($targetPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor tujuan kosong. Isi nomor bisnis di pengaturan atau kirim nomor manual saat test.',
            ], 422);
        }

        $businessName = trim((string) ($settings->business_name ?? 'Rafen'));
        $message = "✅ Test Meta Cloud API berhasil diproses.\n"
            ."Tenant: {$businessName}\n"
            .'Waktu: '.now()->format('d/m/Y H:i:s');

        $result = $service->sendTextMessage($targetPhone, $message);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? 'Pesan test berhasil dikirim ke Meta Cloud API.'
                : ('Gagal kirim test Meta: '.($result['message'] ?: 'unknown error')),
            'http_status' => $result['status'],
            'recipient' => $result['recipient'],
            'meta_response' => $result['data'],
        ], $result['ok'] ? 200 : 422);
    }

    public function testWaYCloud(TestYCloudWhatsAppRequest $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $apiKey = trim((string) ($request->validated('ycloud_api_key') ?? $settings->ycloud_api_key ?? ''));
        $phoneNumberId = trim((string) ($request->validated('ycloud_phone_number_id') ?? $settings->ycloud_phone_number_id ?? ''));
        $wabaId = trim((string) ($request->validated('ycloud_waba_id') ?? $settings->ycloud_waba_id ?? ''));
        $businessNumber = trim((string) ($request->validated('ycloud_business_number') ?? $settings->ycloud_business_number ?? ''));

        $service = new YCloudWhatsAppService(
            apiKey: $apiKey,
            phoneNumberId: $phoneNumberId,
            baseUrl: (string) config('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2'),
            wabaId: $wabaId,
            businessNumber: $businessNumber,
        );

        $targetPhone = trim((string) ($request->validated('phone') ?? ''));
        if ($targetPhone === '') {
            $targetPhone = trim((string) ($settings->business_phone ?? ''));
        }

        if (! $service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'YCloud tenant belum dikonfigurasi. Isi API key dan phone number id lebih dulu.',
            ], 422);
        }

        if ($targetPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor tujuan kosong. Isi nomor bisnis di pengaturan atau kirim nomor manual saat test.',
            ], 422);
        }

        if ($businessNumber !== '' && $service->normalizeRecipient($targetPhone) === $service->normalizeRecipient($businessNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor tujuan test tidak boleh sama dengan nomor bisnis YCloud. Isi nomor lain pada kolom test.',
            ], 422);
        }

        $businessName = trim((string) ($settings->business_name ?? 'Rafen'));
        $message = "✅ Test YCloud berhasil diproses.\n"
            ."Tenant: {$businessName}\n"
            .'Waktu: '.now()->format('d/m/Y H:i:s');

        $result = $service->sendTextMessage($targetPhone, $message);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? 'Pesan test berhasil dikirim ke YCloud.'
                : ('Gagal kirim test YCloud: '.($result['message'] ?: 'unknown error')),
            'http_status' => $result['status'],
            'recipient' => $result['recipient'],
            'ycloud_response' => $result['data'],
        ], $result['ok'] ? 200 : 422);
    }

    public function fetchYCloudPhoneNumbers(FetchYCloudPhoneNumbersRequest $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $apiKey = trim((string) ($request->validated('ycloud_api_key') ?? $settings->ycloud_api_key ?? ''));
        $wabaId = trim((string) ($request->validated('ycloud_waba_id') ?? $settings->ycloud_waba_id ?? ''));
        $service = new YCloudWhatsAppService(
            apiKey: $apiKey,
            phoneNumberId: null,
            baseUrl: (string) config('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2'),
            wabaId: $wabaId,
        );

        if (! $service->hasApiKey()) {
            return response()->json([
                'success' => false,
                'message' => 'API key YCloud belum diisi.',
            ], 422);
        }

        $result = $service->listPhoneNumbers($wabaId);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'http_status' => $result['status'],
            'phone_numbers' => $result['phone_numbers'],
            'ycloud_response' => $result['data'],
        ], $result['ok'] ? 200 : 422);
    }

    public function serviceControl(Request $request, WaMultiSessionManager $manager, string $action)
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        if (! in_array($action, ['status', 'restart'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Aksi service tidak valid.',
            ], 422);
        }

        $result = match ($action) {
            'status' => [
                'success' => true,
                'message' => 'Status service berhasil diambil.',
                'data' => $manager->status(),
            ],
            'restart' => $manager->restart(),
        };

        return response()->json($result, ($result['success'] ?? false) ? 200 : 500);
    }

    public function sessionControl(Request $request, string $action)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        if (! in_array($action, ['status', 'restart'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Aksi sesi tidak valid.',
            ], 422);
        }

        $settings = $this->resolveWaSettingsForRequest($request);

        if (! $settings || ! $settings->hasWaConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'WA Gateway belum dikonfigurasi untuk tenant ini.',
            ], 422);
        }

        $service = WaGatewayService::forTenant($settings);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'WA Gateway tidak dapat diinisialisasi.',
            ], 422);
        }

        $sessionId = $this->resolveSessionForRequest($request, (int) $settings->user_id);
        if ($sessionId !== null) {
            $service->setSessionId($sessionId);
        }

        $result = match ($action) {
            'status' => $service->sessionStatus(),
            'restart' => $service->restartSession(),
        };

        return response()->json([
            'success' => $result['status'] ?? false,
            'message' => $result['message'] ?? 'Tidak ada respons.',
            'data' => $result['data'] ?? null,
            'http_status' => $result['http_status'] ?? null,
            'network_error' => $result['network_error'] ?? false,
        ], ($result['status'] ?? false) ? 200 : 500);
    }

    public function waDevices(Request $request)
    {
        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $devices = WaMultiSessionDevice::query()
            ->forOwner((int) $settings->user_id)
            ->orderByDesc('is_default')
            ->orderBy('device_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function storeWaDevice(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'device_name' => 'required|string|max:120',
            'wa_number' => 'nullable|string|max:30|regex:/^\d+$/',
            'session_id' => 'nullable|string|max:150|regex:/^[a-zA-Z0-9._-]+$/',
            'is_warmup' => 'nullable|boolean',
            'warmup_until' => 'nullable|date',
            'warmup_max_per_batch' => 'nullable|integer|min:0|max:100',
        ]);

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $ownerId = (int) $settings->user_id;
        $deviceCount = WaMultiSessionDevice::query()->forOwner($ownerId)->count();
        $sessionId = trim((string) ($validated['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = 'tenant-'.$ownerId.'-'.Str::slug($validated['device_name'], '-');
        }

        if (WaMultiSessionDevice::query()->where('session_id', $sessionId)->exists()) {
            $sessionId .= '-'.Str::lower(Str::random(4));
        }

        $waNumber = trim((string) ($validated['wa_number'] ?? ''));
        if ($waNumber !== '' && str_starts_with($waNumber, '0')) {
            $waNumber = '62'.substr($waNumber, 1);
        }
        $meta = $this->buildWarmupMetaFromInput($validated, [], $request->user());

        $device = WaMultiSessionDevice::query()->create([
            'user_id' => $ownerId,
            'device_name' => $validated['device_name'],
            'session_id' => $sessionId,
            'wa_number' => $waNumber !== '' ? $waNumber : null,
            'is_default' => $deviceCount === 0,
            'is_active' => true,
            'meta' => $meta,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device WA berhasil ditambahkan.',
            'data' => $device,
        ]);
    }

    public function updateWaDeviceWarmup(Request $request, WaMultiSessionDevice $device)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'is_warmup' => 'required|boolean',
            'warmup_until' => 'nullable|date',
            'warmup_max_per_batch' => 'nullable|integer|min:0|max:100',
        ]);

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings || $device->user_id !== (int) $settings->user_id) {
            abort(404);
        }

        $existingMeta = is_array($device->meta) ? $device->meta : [];
        $meta = $this->buildWarmupMetaFromInput($validated, $existingMeta, $request->user());

        $device->update(['meta' => $meta]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan warmup device berhasil diperbarui.',
            'data' => $device->fresh(),
        ]);
    }

    public function setDefaultWaDevice(Request $request, WaMultiSessionDevice $device)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings || $device->user_id !== (int) $settings->user_id) {
            abort(404);
        }

        WaMultiSessionDevice::query()
            ->forOwner($device->user_id)
            ->update(['is_default' => false]);

        WaMultiSessionDevice::query()
            ->whereKey($device->id)
            ->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Device default berhasil diperbarui.',
        ]);
    }

    public function destroyWaDevice(Request $request, WaMultiSessionDevice $device)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings || $device->user_id !== (int) $settings->user_id) {
            abort(404);
        }

        $wasDefault = $device->is_default;
        $ownerId = $device->user_id;
        $sessionId = $device->session_id;

        // Hapus credentials dari auth_store agar sesi baru tidak load creds lama
        DB::table('wa_multi_session_auth_store')
            ->where('session_id', $sessionId)
            ->delete();

        // Stop session di gateway (best-effort, jangan blok kalau gateway down)
        try {
            $baseUrl = rtrim((string) config('wa.multi_session.host', '127.0.0.1'), '/');
            $port = (int) config('wa.multi_session.port', 3100);
            $token = (string) config('wa.multi_session.auth_token', '');
            Http::timeout(5)
                ->withToken($token)
                ->post("http://{$baseUrl}:{$port}/api/v2/sessions/stop", ['session' => $sessionId, 'keep_credentials' => false]);
        } catch (\Throwable) {
            // gateway mungkin down, lanjut hapus device
        }

        $device->delete();

        if ($wasDefault) {
            $nextDevice = WaMultiSessionDevice::query()
                ->forOwner($ownerId)
                ->orderBy('id')
                ->limit(1)
                ->first();

            if ($nextDevice) {
                $nextDevice->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Device WA berhasil dihapus.',
        ]);
    }

    public function testWaDevice(Request $request, WaMultiSessionDevice $device)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings || $device->user_id !== (int) $settings->user_id) {
            abort(404);
        }

        if (! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.'], 422);
        }

        $csPhone = trim((string) ($settings->business_phone ?? ''));
        if (empty($csPhone)) {
            return response()->json(['success' => false, 'message' => 'Nomor HP bisnis belum diisi di Pengaturan Bisnis.'], 422);
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return response()->json(['success' => false, 'message' => 'WA Gateway tidak dapat diinisialisasi.'], 422);
        }

        $service->setSessionId($device->session_id);

        // Cek status sesi sebelum kirim agar error message lebih informatif
        $sessionCheck = $service->sessionStatus();
        $sessionStatus = strtolower((string) ($sessionCheck['data']['status'] ?? ''));
        if ($sessionStatus !== 'connected') {
            $label = match ($sessionStatus) {
                'connecting', 'awaiting_qr' => 'belum scan QR — buka Manajemen Device lalu klik Scan QR',
                'disconnected', 'error' => 'terputus — coba Restart Sesi lalu scan QR ulang',
                'stopped', 'idle' => 'tidak aktif — coba Restart Sesi',
                default => "status: {$sessionStatus}",
            };

            return response()->json(['success' => false, 'message' => "Device \"{$device->device_name}\" {$label}."], 422);
        }

        $businessName = trim((string) ($settings->business_name ?? '')) ?: 'ISP';
        $message = "✅ *Test Koneksi WA Berhasil*\n\n"
            ."Device *{$device->device_name}* ({$device->session_id}) berhasil terhubung dan siap mengirim pesan.\n\n"
            ."Dikirim dari: {$businessName}\n"
            .now()->format('d/m/Y H:i');

        $phone = '62'.ltrim(preg_replace('/[^0-9]/', '', $csPhone), '0');
        $sent = $service->sendMessage($phone, $message, [
            'event' => 'blast',
            'name' => 'Test Device',
        ]);

        if ($sent) {
            return response()->json(['success' => true, 'message' => "Pesan test berhasil dikirim ke {$csPhone} via device {$device->device_name}."]);
        }

        // Ambil reason dari log terbaru untuk pesan error yang lebih spesifik
        $lastLog = WaBlastLog::query()
            ->where('owner_id', (int) $settings->user_id)
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->value('reason');
        $errDetail = $lastLog ? " ({$lastLog})" : '';

        return response()->json(['success' => false, 'message' => "Pesan tidak terkirim{$errDetail}."], 422);
    }

    public function resetWaStickySender(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'tenant_id' => 'nullable|integer',
            'phone' => 'required|string|max:30',
        ]);

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $ownerId = (int) $settings->user_id;
        $removed = WaGatewayService::clearStickySenderForPhone($ownerId, (string) $validated['phone']);

        if ($removed) {
            return response()->json([
                'success' => true,
                'message' => 'Mapping pengirim untuk nomor tersebut berhasil direset.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Mapping pengirim tidak ditemukan atau format nomor tidak valid.',
        ], 422);
    }

    public function testTemplate(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $request->validate([
            'type' => 'required|in:registration,invoice,payment',
            'tenant_id' => 'nullable|integer',
        ]);

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json(['success' => false, 'message' => 'Tenant tidak ditemukan.'], 422);
        }

        if (! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.']);
        }

        $csNumber = $settings->business_phone ?? '';
        if (empty(trim($csNumber))) {
            return response()->json(['success' => false, 'message' => 'Nomor HP bisnis (CS) belum diisi di Pengaturan.']);
        }

        $template = $settings->getTemplate($request->type);

        $owner = User::query()->find((int) $settings->user_id);
        $bankAccounts = $owner
            ? $owner->bankAccounts()->active()->get()
            : collect();
        $bankLines = $bankAccounts->map(fn ($b) => $b->bank_name.' '.$b->account_number.' a/n '.$b->account_name)->join("\n");
        if (empty(trim($bankLines))) {
            $bankLines = '(Belum ada rekening bank aktif)';
        }

        $message = str_replace(
            ['{name}', '{username}', '{service}', '{profile}', '{due_date}', '{invoice_no}', '{total}', '{paid_at}', '{customer_id}', '{cs_number}', '{bank_account}'],
            ['Bapak/Ibu Test', 'test_user', 'PPPoE', 'Paket 10Mbps', date('d/m/Y'), 'INV-TEST001', 'Rp 150.000', now()->format('d/m/Y H:i'), 'CUST-001', $csNumber, $bankLines],
            $template
        );

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return response()->json(['success' => false, 'message' => 'WA Gateway tidak dapat diinisialisasi.']);
        }

        $phone = '62'.ltrim(preg_replace('/[^0-9]/', '', $csNumber), '0');

        try {
            $service->sendMessage($phone, '[TEST TEMPLATE] '."\n\n".$message);

            return response()->json(['success' => true, 'message' => 'Pesan test berhasil dikirim ke '.$csNumber]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal kirim: '.$e->getMessage()]);
        }
    }

    public function uploadLogo(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $request->validate([
            'business_logo' => 'required|image|max:2048',
        ]);

        $this->storeTenantLogo(
            settings: $request->user()->getSettings(),
            uploadedFile: $request->file('business_logo'),
            targetDirectory: 'business-logos',
            column: 'business_logo'
        );

        return back()->with('success', config('license.self_hosted_enabled', false)
            ? 'Logo sistem berhasil diunggah.'
            : 'Logo tenant berhasil diunggah.');
    }

    public function uploadInvoiceLogo(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $request->validate([
            'invoice_logo' => 'required|image|max:2048',
        ]);

        $this->storeTenantLogo(
            settings: $request->user()->getSettings(),
            uploadedFile: $request->file('invoice_logo'),
            targetDirectory: 'invoice-logos',
            column: 'invoice_logo'
        );

        return back()->with('success', 'Logo nota berhasil diunggah.');
    }

    public function updateIsolir(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'isolir_page_title' => 'nullable|string|max:255',
            'isolir_page_body' => 'nullable|string|max:2000',
            'isolir_page_contact' => 'nullable|string|max:255',
            'isolir_page_bg_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'isolir_page_accent_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
        ]);

        $user = $request->user();
        $settings = $user->getSettings();
        $settings->update($validated);

        return back()->with('success', 'Pengaturan halaman isolir berhasil disimpan.');
    }

    public function isolirPreview(Request $request)
    {
        return app(IsolirPageController::class)->preview($request);
    }

    public function updateGenieacs(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'genieacs_url' => 'nullable|url|max:255',
            'genieacs_username' => 'nullable|string|max:64',
            'genieacs_password' => 'nullable|string|max:128',
            'genieacs_cr_username' => 'nullable|string|max:64',
            'genieacs_cr_password' => 'nullable|string|max:128',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();
        $settings->update($validated);

        return back()->with('success', 'Pengaturan GenieACS berhasil disimpan.');
    }

    private function storeTenantLogo(TenantSettings $settings, UploadedFile $uploadedFile, string $targetDirectory, string $column): void
    {
        $currentPath = $settings->{$column};
        if (! empty($currentPath)) {
            \Storage::disk('public')->delete($currentPath);
        }

        $path = $uploadedFile->store($targetDirectory, 'public');
        $settings->update([$column => $path]);
    }

    public function getWaGroups(Request $request)
    {
        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json(['success' => false, 'message' => 'Tenant tidak ditemukan.'], 422);
        }

        if (! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.'], 422);
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return response()->json(['success' => false, 'message' => 'WA Gateway tidak dapat diinisialisasi.'], 422);
        }

        $sessionId = $this->resolveSessionForRequest($request, (int) $settings->user_id);
        if ($sessionId !== null) {
            $service->setSessionId($sessionId);
        }

        $groups = $service->getGroups();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    public function updateTicketGroup(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'wa_ticket_group_id' => 'nullable|string|max:255',
            'wa_ticket_group_name' => 'nullable|string|max:255',
            'tenant_id' => 'nullable|integer',
        ]);

        $settings = $this->resolveWaSettingsForRequest($request);
        if (! $settings) {
            return response()->json(['success' => false, 'message' => 'Tenant tidak ditemukan.'], 422);
        }

        $settings->update([
            'wa_ticket_group_id' => $validated['wa_ticket_group_id'] ?? null,
            'wa_ticket_group_name' => $validated['wa_ticket_group_name'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Grup notifikasi tiket berhasil disimpan.']);
    }

    private function resolveWaSettingsForRequest(Request $request): ?TenantSettings
    {
        $user = $request->user();

        if ($user->isSuperAdmin() && $request->integer('tenant_id')) {
            $tenant = User::query()
                ->tenants()
                ->where('id', $request->integer('tenant_id'))
                ->first();

            return $tenant ? TenantSettings::getOrCreate($tenant->id) : null;
        }

        return $user->getSettings();
    }

    private function resolveSessionForRequest(Request $request, int $ownerId): ?string
    {
        $directSession = trim((string) $request->input('session_id', ''));
        if ($directSession !== '') {
            return $directSession;
        }

        $deviceId = $request->integer('device_id');
        if ($deviceId) {
            $device = WaMultiSessionDevice::query()
                ->forOwner($ownerId)
                ->whereKey($deviceId)
                ->first();

            return $device?->session_id;
        }

        $default = WaMultiSessionDevice::query()
            ->forOwner($ownerId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        return $default?->session_id;
    }

    private function ensureLocalWaGatewayParameters(TenantSettings $settings): void
    {
        $gatewayUrl = trim((string) config('wa.multi_session.public_url', ''));
        $configuredToken = trim((string) config('wa.multi_session.auth_token', ''));
        $configuredKey = trim((string) config('wa.multi_session.master_key', ''));
        $webhookSecret = trim((string) ($settings->wa_webhook_secret ?? ''));

        $payload = [
            'wa_gateway_url' => $gatewayUrl,
            'wa_gateway_token' => $configuredToken !== '' ? $configuredToken : (string) ($settings->wa_gateway_token ?? ''),
            'wa_gateway_key' => $configuredKey !== '' ? $configuredKey : (string) ($settings->wa_gateway_key ?? ''),
            'wa_webhook_secret' => $webhookSecret !== '' ? $webhookSecret : 'tenant-'.$settings->user_id,
        ];

        $hasChange = false;
        foreach ($payload as $key => $value) {
            if ((string) ($settings->{$key} ?? '') !== (string) $value) {
                $hasChange = true;
                break;
            }
        }

        if ($hasChange) {
            $settings->update($payload);
            $settings->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $baseMeta
     * @return array<string, mixed>
     */
    private function buildWarmupMetaFromInput(array $input, array $baseMeta = [], ?User $actor = null): array
    {
        $meta = $baseMeta;
        $isWarmup = (bool) ($input['is_warmup'] ?? false);
        $meta['is_warmup'] = $isWarmup;

        $warmupUntilRaw = trim((string) ($input['warmup_until'] ?? ''));
        $manualWarmupUntil = $warmupUntilRaw !== '';
        $hasWarmupMaxInput = array_key_exists('warmup_max_per_batch', $input)
            && trim((string) ($input['warmup_max_per_batch'] ?? '')) !== '';
        $requestedWarmupMax = $hasWarmupMaxInput ? (int) ($input['warmup_max_per_batch'] ?? 0) : null;
        $autoWarmupByMaxZero = $hasWarmupMaxInput && $requestedWarmupMax === 0;
        $manualWarmupMax = $hasWarmupMaxInput && $requestedWarmupMax !== 0;

        if ($isWarmup) {
            $startedAt = trim((string) ($meta['warmup_started_at'] ?? ''));
            if ($startedAt === '') {
                $startedAt = now()->toIso8601String();
                $meta['warmup_started_at'] = $startedAt;
            }

            $meta['warmup_auto'] = ! $manualWarmupUntil && (! $hasWarmupMaxInput || $autoWarmupByMaxZero);

            if ($manualWarmupUntil) {
                $meta['warmup_until'] = Carbon::parse($warmupUntilRaw)->toIso8601String();
            } elseif (trim((string) ($meta['warmup_until'] ?? '')) === '') {
                $meta['warmup_until'] = Carbon::parse($startedAt)->addDays(14)->toIso8601String();
            }

            if ($manualWarmupMax) {
                $maxPerBatch = $requestedWarmupMax ?? 1;
                $meta['warmup_max_per_batch'] = max(1, min(100, $maxPerBatch));
            } elseif (! isset($meta['warmup_max_per_batch'])) {
                $meta['warmup_max_per_batch'] = 1;
            }
        } else {
            unset($meta['warmup_until'], $meta['warmup_started_at'], $meta['warmup_auto']);
            $meta['warmup_max_per_batch'] = 1;
        }

        $history = is_array($meta['warmup_history'] ?? null) ? $meta['warmup_history'] : [];
        $history[] = [
            'changed_at' => now()->toIso8601String(),
            'changed_by_id' => $actor?->id,
            'changed_by_name' => $actor?->name,
            'is_warmup' => $meta['is_warmup'],
            'warmup_auto' => (bool) ($meta['warmup_auto'] ?? false),
            'warmup_until' => $meta['warmup_until'] ?? null,
            'warmup_max_per_batch' => $meta['warmup_max_per_batch'],
        ];
        $meta['warmup_history'] = array_slice($history, -30);

        return $meta;
    }
}
