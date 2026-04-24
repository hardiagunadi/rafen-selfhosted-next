<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendVoucherWaRequest;
use App\Http\Requests\StoreVoucherBatchRequest;
use App\Models\HotspotProfile;
use App\Models\TenantSettings;
use App\Models\Voucher;
use App\Models\WaBlastLog;
use App\Services\VoucherGeneratorService;
use App\Services\WaGatewayService;
use App\Services\YCloudWhatsAppService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VoucherController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly VoucherGeneratorService $generator) {}

    public function datatable(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);
        $search = $request->input('search.value', '');
        $status = $request->input('status', '');
        $batch = $request->input('batch', '');

        $query = Voucher::query()->with(['hotspotProfile'])->accessibleBy($currentUser);

        if ($search !== '') {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($status !== '' && in_array($status, ['unused', 'used', 'expired'])) {
            $query->where('status', $status);
        }

        if ($batch !== '' && $batch !== 'null' && $batch !== 'undefined') {
            $query->where('batch_name', $batch);
        }

        $filtered = (clone $query)->count();
        $total = $filtered;

        $vouchers = $query->latest()->skip($start)->take($length > 0 ? $length : 20)->get();
        $settingsCache = [];

        $data = $vouchers->map(function (Voucher $voucher) use (&$settingsCache) {
            $statusColor = match ($voucher->status) {
                'unused' => 'success',
                'used' => 'info',
                'expired' => 'secondary',
                default => 'light',
            };
            if ($voucher->status === 'unused') {
                $statusLabel = 'Belum Login';
            } elseif ($voucher->status === 'used') {
                $loginDate = $voucher->used_at?->format('d/m/Y H:i') ?? '-';
                $statusLabel = 'Aktif ('.$loginDate.')';
            } else {
                $statusLabel = strtoupper((string) $voucher->status);
            }
            $statusBadge = '<span class="badge badge-'.$statusColor.'">'.$statusLabel.'</span>';

            $isUnused = $voucher->status === 'unused';
            $checkbox = '<input type="checkbox" name="ids[]" value="'.$voucher->id.'"'.($isUnused ? '' : ' disabled').'>';
            $ownerId = (int) $voucher->owner_id;

            if (! isset($settingsCache[$ownerId])) {
                $settingsCache[$ownerId] = TenantSettings::getOrCreate($ownerId);
            }

            $settings = $settingsCache[$ownerId];
            $providerOptions = $this->availableVoucherWaProviders($settings);
            $providerOptionsJson = e((string) json_encode($providerOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
            $defaultProvider = e($this->resolveVoucherWaProvider($settings, ''));
            $waConfigured = $providerOptions !== [];
            $sendWaButton = $isUnused && $waConfigured
                ? '<button type="button" class="btn btn-sm btn-success btn-send-voucher-wa"'
                    .' data-send-wa-url="'.route('vouchers.send-wa', $voucher).'"'
                    .' data-provider-options="'.$providerOptionsJson.'"'
                    .' data-default-provider="'.$defaultProvider.'"'
                    .' data-voucher-code="'.e($voucher->code).'"'
                    .' data-voucher-profile="'.e($voucher->hotspotProfile?->name ?? '-').'"'
                    .' title="Kirim kode voucher ke WhatsApp"><i class="fab fa-whatsapp"></i></button>'
                : '<button class="btn btn-sm btn-light" disabled title="WhatsApp belum tersedia"><i class="fab fa-whatsapp"></i></button>';
            $deleteButton = $isUnused
                ? '<button class="btn btn-sm btn-danger" data-ajax-delete="'.route('vouchers.destroy', $voucher).'" data-confirm="Hapus voucher '.$voucher->code.'?"><i class="fas fa-trash"></i></button>'
                : '<button class="btn btn-sm btn-light" disabled><i class="fas fa-trash"></i></button>';
            $aksi = '<div class="btn-group btn-group-sm">'.$sendWaButton.$deleteButton.'</div>';

            return [
                'checkbox' => $checkbox,
                'code' => '<code class="font-weight-bold">'.$voucher->code.'</code>',
                'batch' => $voucher->batch_name ?? '-',
                'profil' => $voucher->hotspotProfile?->name ?? '-',
                'status' => $statusBadge,
                'expired' => $voucher->expired_at?->format('Y-m-d') ?? '-',
                'aksi' => '<div class="text-right">'.$aksi.'</div>',
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function index(Request $request): View
    {
        $currentUser = $request->user();

        $stats = [
            'unused' => Voucher::query()->accessibleBy($currentUser)->where('status', 'unused')->count(),
            'used' => Voucher::query()->accessibleBy($currentUser)->where('status', 'used')->count(),
            'expired' => Voucher::query()->accessibleBy($currentUser)->where('status', 'expired')->count(),
        ];

        $batches = Voucher::query()->accessibleBy($currentUser)->whereNotNull('batch_name')->distinct()->pluck('batch_name');

        return view('vouchers.index', compact('stats', 'batches'));
    }

    public function create(Request $request): View
    {
        $currentUser = $request->user();
        $profiles = HotspotProfile::query()->accessibleBy($currentUser)->orderBy('name')->get();

        return view('vouchers.create', compact('profiles'));
    }

    public function store(StoreVoucherBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        $profile = HotspotProfile::query()->accessibleBy($currentUser)->findOrFail($validated['hotspot_profile_id']);

        $this->generator->generateBatch(
            profile: $profile,
            count: (int) $validated['jumlah'],
            batchName: $validated['batch_name'],
            owner: $currentUser
        );

        return redirect()->route('vouchers.index')->with('status', "Batch voucher '{$validated['batch_name']}' berhasil dibuat.");
    }

    public function printBatch(Request $request, string $batch): View
    {
        $currentUser = $request->user();
        $vouchers = Voucher::query()
            ->accessibleBy($currentUser)
            ->where('batch_name', $batch)
            ->with('hotspotProfile')
            ->get();

        return view('vouchers.print', compact('vouchers', 'batch'));
    }

    public function sendWa(SendVoucherWaRequest $request, Voucher $voucher): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $canSendWa = $user->isSuperAdmin() || $user->isAdmin() || in_array($user->role, ['keuangan', 'noc', 'it_support', 'cs'], true);

        if (! $canSendWa) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && (int) $voucher->owner_id !== (int) $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($voucher->status !== 'unused') {
            return $this->sendWaErrorResponse($request, 'Hanya voucher yang belum login yang bisa dikirim ke WhatsApp.');
        }

        $settings = TenantSettings::getOrCreate((int) $voucher->owner_id);

        $selectedProvider = $this->resolveVoucherWaProvider(
            $settings,
            (string) ($request->validated('provider') ?? '')
        );
        $providerError = $this->validateVoucherWaProviderAvailability($settings, $selectedProvider);

        if ($providerError !== null) {
            return $this->sendWaErrorResponse($request, $providerError);
        }

        $phone = trim((string) $request->validated('phone'));
        $message = $this->buildVoucherWaMessage($voucher->loadMissing('hotspotProfile'));
        $context = [
            'event' => 'voucher_code',
            'provider' => $selectedProvider,
            'user_id' => $voucher->id,
            'username' => $voucher->username ?? $voucher->code,
            'name' => 'Voucher '.$voucher->code,
            'message' => $message,
        ];

        if ($selectedProvider === 'ycloud') {
            $service = YCloudWhatsAppService::forTenant($settings);
            if (! $service) {
                return $this->sendWaErrorResponse($request, 'Konfigurasi YCloud tenant belum lengkap.');
            }

            $templateName = $settings->getYCloudTemplateName('voucher_code');
            $result = $service->sendTemplateMessage($phone, $templateName, 'id', [[
                'type' => 'body',
                'parameters' => [[
                    'type' => 'text',
                    'text' => $message,
                ]],
            ]]);

            if (! $result['ok']) {
                $this->writeYCloudVoucherLog($voucher, $phone, 'failed', $message, $result, $templateName);

                return $this->sendWaErrorResponse($request, 'Kode voucher gagal dikirim ke YCloud. '.($result['message'] ?: ''));
            }

            $this->writeYCloudVoucherLog($voucher, $phone, 'sent', $message, $result, $templateName);
        } else {
            $waService = WaGatewayService::forTenant($settings);
            if (! $waService) {
                return $this->sendWaErrorResponse($request, 'WA Gateway lokal tidak dapat diinisialisasi.');
            }

            $isSent = $waService->sendMessage($phone, $message, $context);

            if (! $isSent) {
                return $this->sendWaErrorResponse($request, 'Kode voucher gagal dikirim ke WhatsApp.');
            }
        }

        $this->logActivity('send_wa', 'Voucher', $voucher->id, $voucher->code, (int) $voucher->owner_id);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Kode voucher berhasil dikirim ke '.$phone]);
        }

        return redirect()->back()->with('status', 'Kode voucher berhasil dikirim ke '.$phone);
    }

    public function destroy(Voucher $voucher): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $voucher->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($voucher->status !== 'unused') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Hanya voucher unused yang dapat dihapus.'], 422);
            }

            return redirect()->route('vouchers.index')->with('error', 'Hanya voucher unused yang dapat dihapus.');
        }

        $voucher->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Voucher dihapus.']);
        }

        return redirect()->route('vouchers.index')->with('status', 'Voucher dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            Voucher::query()->whereIn('id', $ids)->accessibleBy($user)->where('status', 'unused')->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Voucher terpilih dihapus.']);
        }

        return redirect()->route('vouchers.index')->with('status', 'Voucher terpilih dihapus.');
    }

    private function buildVoucherWaMessage(Voucher $voucher): string
    {
        $profileName = $voucher->hotspotProfile?->name ?? '-';
        $batchName = $voucher->batch_name ?: '-';
        $expiredAt = $voucher->expired_at?->format('d/m/Y H:i') ?? '-';

        return "*Kode Voucher Hotspot*\n\n"
            ."Kode Voucher: {$voucher->code}\n"
            .'Username: '.($voucher->username ?: $voucher->code)."\n"
            .'Password: '.($voucher->password ?: $voucher->code)."\n"
            ."Profil: {$profileName}\n"
            ."Batch: {$batchName}\n"
            ."Expired: {$expiredAt}\n\n"
            .'Silakan gunakan kode di atas untuk login hotspot.';
    }

    private function sendWaErrorResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['error' => $message], 422);
        }

        return redirect()->back()->with('error', $message);
    }

    /**
     * @return array<int, array{value: string, label: string, hint: string}>
     */
    private function availableVoucherWaProviders(TenantSettings $settings): array
    {
        $providers = [];

        if ($settings->hasLocalWaConfigured() && WaGatewayService::forTenant($settings)) {
            $providers[] = [
                'value' => 'local',
                'label' => 'Gateway Lokal',
                'hint' => 'Kirim melalui device / session WhatsApp lokal.',
            ];
        }

        if ($settings->hasYCloudConfigured()) {
            $providers[] = [
                'value' => 'ycloud',
                'label' => 'YCloud',
                'hint' => 'Kirim melalui WhatsApp API YCloud.',
            ];
        }

        return $providers;
    }

    private function resolveVoucherWaProvider(TenantSettings $settings, string $requestedProvider): string
    {
        return match ($requestedProvider) {
            'local' => 'local',
            'ycloud' => 'ycloud',
            default => $settings->usesYCloud() ? 'ycloud' : 'local',
        };
    }

    private function validateVoucherWaProviderAvailability(TenantSettings $settings, string $provider): ?string
    {
        if ($provider === 'ycloud') {
            return $settings->hasYCloudConfigured()
                ? null
                : 'YCloud belum dikonfigurasi lengkap di Pengaturan WhatsApp.';
        }

        return WaGatewayService::forTenant($settings)
            ? null
            : 'WA Gateway lokal belum dikonfigurasi di Pengaturan WhatsApp.';
    }

    /**
     * @param  array{recipient: string, message: string, provider_message_id: string|null, delivery_status: string|null, pricing_metadata: array<mixed>}  $result
     */
    private function writeYCloudVoucherLog(Voucher $voucher, string $phone, string $status, string $message, array $result, string $templateName): void
    {
        $actor = Auth::user();
        $service = new YCloudWhatsAppService;
        $normalizedPhone = $service->normalizeRecipient($phone);

        WaBlastLog::create([
            'owner_id' => $voucher->owner_id,
            'sent_by_id' => $actor?->id,
            'sent_by_name' => $actor?->name,
            'event' => 'voucher_code',
            'provider' => 'ycloud',
            'phone' => $phone,
            'phone_normalized' => $normalizedPhone,
            'status' => $status,
            'reason' => $status === 'failed' ? ($result['message'] ?? 'Gagal mengirim ke YCloud.') : null,
            'user_id' => $voucher->id,
            'username' => $voucher->username ?? $voucher->code,
            'customer_name' => 'Voucher '.$voucher->code,
            'ref_id' => $result['provider_message_id'] ?? null,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'delivery_status' => $result['delivery_status'] ?? null,
            'pricing_metadata' => $result['pricing_metadata'] ?? [],
            'template_name' => $templateName,
            'message' => $message,
            'created_at' => now(),
        ]);
    }
}
