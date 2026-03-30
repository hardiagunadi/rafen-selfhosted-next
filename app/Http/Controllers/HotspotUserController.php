<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotspotUserRequest;
use App\Http\Requests\UpdateHotspotUserRequest;
use App\Models\HotspotProfile;
use App\Models\HotspotUser;
use App\Models\ProfileGroup;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\HotspotRadiusSynchronizer;
use App\Services\WaNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotspotUserController extends Controller
{
    public function generateCustomerId(Request $request): JsonResponse
    {
        $ownerId = $request->input('owner_id') ? (int) $request->input('owner_id') : $request->user()->effectiveOwnerId();

        return response()->json(['customer_id' => HotspotUser::generateCustomerId($ownerId)]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = $request->input('search.value', '');
        $filterOnProcess = $request->input('filter_on_process', '');

        $query = HotspotUser::query()
            ->with(['owner', 'hotspotProfile', 'assignedTeknisi'])
            ->accessibleBy($currentUser);

        if ($filterOnProcess === '1') {
            $query->where('status_registrasi', 'on_process');
        }

        $total = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $filtered = (clone $query)->count();

        $users = $query->latest()->skip($start)->take($length > 0 ? $length : 10)->get();

        $currentUserForMap = $currentUser;
        $data = $users->map(function (HotspotUser $user) use ($currentUserForMap) {
            $statusColor = match ($user->status_akun) {
                'enable' => 'success',
                'disable' => 'danger',
                'isolir' => 'warning',
                default => 'secondary',
            };
            $statusBadge = '<span class="badge badge-'.$statusColor.'">'.strtoupper((string) $user->status_akun).'</span>';

            if ($user->jatuh_tempo) {
                $isPast = $user->jatuh_tempo->isPast();
                $due = '<span class="'.($isPast ? 'text-danger' : 'text-primary').' font-weight-bold">'.$user->jatuh_tempo->format('Y-m-d').'</span>';
            } else {
                $due = '<span class="text-muted">-</span>';
            }

            $isTeknisi = $currentUserForMap->isTeknisi();
            $canManage = ! $isTeknisi || is_null($user->assigned_teknisi_id) || $user->assigned_teknisi_id === $currentUserForMap->id;

            $perpanjang = $canManage
                ? '<div class="btn-group btn-group-sm"><button class="btn btn-success" data-ajax-post="'.route('hotspot-users.renew', $user).'" data-confirm="Perpanjang layanan hotspot ini?"><i class="fas fa-redo-alt mr-1"></i>Perpanjang</button></div>'
                : '';

            if ($canManage) {
                $aksi = '<div class="btn-group btn-group-sm">'.
                    '<a href="'.route('hotspot-users.edit', $user).'" class="btn btn-warning text-white" title="Edit"><i class="fas fa-pen"></i></a>'.
                    '<button type="button" class="btn btn-warning dropdown-toggle dropdown-toggle-split text-white" data-toggle="dropdown"></button>'.
                    '<div class="dropdown-menu dropdown-menu-right">'.
                        '<button class="dropdown-item text-danger" data-ajax-delete="'.route('hotspot-users.destroy', $user).'" data-confirm="Hapus user hotspot ini?"><i class="fas fa-trash mr-1"></i>Hapus</button>'.
                    '</div>'.
                    '</div>';
            } else {
                $aksi = '<span class="text-muted small">Read-only</span>';
            }

            $teknisiLabel = $user->assignedTeknisi ? '<span class="badge badge-info">'.e($user->assignedTeknisi->name).'</span>' : '<span class="text-muted">-</span>';

            return [
                'checkbox' => '<input type="checkbox" name="ids[]" value="'.$user->id.'">',
                'customer_id' => $canManage
                    ? '<a href="#" class="toggle-status-btn badge badge-'.($user->status_akun === 'enable' ? 'success' : 'danger').'" data-toggle-url="'.route('hotspot-users.toggle-status', $user).'" title="Klik untuk '.($user->status_akun === 'enable' ? 'disable' : 'enable').'">'.($user->customer_id ?? '-').'</a>'
                    : '<span class="badge badge-'.($user->status_akun === 'enable' ? 'success' : 'danger').'">'.($user->customer_id ?? '-').'</span>',
                'nama' => '<div class="font-weight-bold text-uppercase">'.$user->customer_name.'</div>',
                'username' => $user->username ?? '-',
                'profil' => $user->hotspotProfile?->name ?? '-',
                'status' => $statusBadge,
                'jatuh_tempo' => $due,
                'teknisi' => $teknisiLabel,
                'owner' => $user->owner?->name ?? '-',
                'perpanjang' => $perpanjang,
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

    public function autocomplete(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('q', ''));

        if (mb_strlen($keyword) < 2) {
            return response()->json(['data' => []]);
        }

        $users = HotspotUser::query()
            ->accessibleBy($request->user())
            ->where(function ($query) use ($keyword): void {
                $query->where('customer_name', 'like', "%{$keyword}%")
                    ->orWhere('customer_id', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%");
            })
            ->latest()
            ->limit(8)
            ->get(['id', 'customer_name', 'customer_id', 'username']);

        $data = $users->map(function (HotspotUser $user): array {
            $displayName = trim((string) ($user->customer_name ?: $user->username ?: $user->customer_id));

            return [
                'value' => $displayName,
                'label' => sprintf(
                    '%s | %s | %s',
                    $user->customer_id ?? '-',
                    $user->username ?? '-',
                    $user->customer_name ?? '-',
                ),
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $currentUser = $request->user();

        $query = HotspotUser::query()->with(['owner', 'hotspotProfile', 'profileGroup']);

        $query->accessibleBy($currentUser);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage > 0 ? $perPage : 10)->withQueryString();
        $users->getCollection()->each(fn (HotspotUser $user) => $this->enforceOverdueAction($user));

        $now = now();
        $stats = [
            'registrasi_bulan_ini' => HotspotUser::query()->accessibleBy($currentUser)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'pelanggan_isolir' => HotspotUser::query()->accessibleBy($currentUser)->where('status_akun', 'isolir')->count(),
            'akun_disable' => HotspotUser::query()->accessibleBy($currentUser)->where('status_akun', 'disable')->count(),
            'total' => HotspotUser::query()->accessibleBy($currentUser)->count(),
        ];

        return view('hotspot_users.index', compact('users', 'stats', 'perPage', 'search'));
    }

    public function create(): View
    {
        $currentUser = auth()->user();

        return view('hotspot_users.create', [
            'owners' => $currentUser->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$currentUser]),
            'groups' => ProfileGroup::query()->accessibleBy($currentUser)->orderBy('name')->get(),
            'profiles' => HotspotProfile::query()->accessibleBy($currentUser)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreHotspotUserRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        $user = HotspotUser::create($data);

        $settings = TenantSettings::getOrCreate((int) ($user->owner_id ?? auth()->user()->effectiveOwnerId()));

        if ($data['status_registrasi'] === 'on_process') {
            WaNotificationService::notifyOnProcess($settings, $user->load('hotspotProfile'));
        } else {
            WaNotificationService::notifyRegistration($settings, $user->load('hotspotProfile'));
        }

        app(HotspotRadiusSynchronizer::class)->syncSingleUser($user);

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot ditambahkan.');
    }

    public function show(HotspotUser $hotspotUser): RedirectResponse
    {
        return redirect()->route('hotspot-users.edit', $hotspotUser);
    }

    public function edit(HotspotUser $hotspotUser): View
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $hotspotUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $teknisiList = collect();
        if (! $currentUser->isTeknisi()) {
            $teknisiQuery = User::query()->where('role', 'teknisi');
            if (! $currentUser->isSuperAdmin()) {
                $teknisiQuery->where('parent_id', $currentUser->effectiveOwnerId());
            }
            $teknisiList = $teknisiQuery->orderBy('name')->get();
        }

        return view('hotspot_users.edit', [
            'hotspotUser' => $hotspotUser,
            'owners' => $currentUser->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$currentUser]),
            'groups' => ProfileGroup::query()->accessibleBy($currentUser)->orderBy('name')->get(),
            'profiles' => HotspotProfile::query()->accessibleBy($currentUser)->orderBy('name')->get(),
            'teknisiList' => $teknisiList,
        ]);
    }

    public function update(UpdateHotspotUserRequest $request, HotspotUser $hotspotUser): RedirectResponse
    {
        $originalStatusRegistrasi = $hotspotUser->status_registrasi;
        $data = $this->prepareData($request->validated());
        $hotspotUser->update($data);

        // ON PROCESS → AKTIF: kirim WA registrasi
        if ($originalStatusRegistrasi === 'on_process' && $hotspotUser->status_registrasi === 'aktif') {
            $settings = TenantSettings::getOrCreate((int) ($hotspotUser->owner_id ?? auth()->user()->effectiveOwnerId()));
            WaNotificationService::notifyRegistration($settings, $hotspotUser->load('hotspotProfile'));
        }

        app(HotspotRadiusSynchronizer::class)->syncSingleUser($hotspotUser);

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot diperbarui.');
    }

    public function destroy(HotspotUser $hotspotUser): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        if ($currentUser->role === 'teknisi') {
            abort(403);
        }

        if (! $currentUser->isSuperAdmin() && $hotspotUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $hotspotUser->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'User Hotspot dihapus.']);
        }

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot dihapus.');
    }

    public function renew(HotspotUser $hotspotUser): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $hotspotUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $profile = $hotspotUser->hotspotProfile;

        $base = $hotspotUser->jatuh_tempo && $hotspotUser->jatuh_tempo->isFuture()
            ? Carbon::parse($hotspotUser->jatuh_tempo)
            : now();

        if ($profile && $profile->masa_aktif_value && $profile->masa_aktif_unit) {
            $unitMap = ['menit' => 'minutes', 'jam' => 'hours', 'hari' => 'days', 'bulan' => 'months'];
            $carbonUnit = $unitMap[$profile->masa_aktif_unit] ?? 'days';
            $newDue = $base->add($carbonUnit, (int) $profile->masa_aktif_value)->endOfDay();
        } else {
            $newDue = $base->addMonth()->endOfDay();
        }

        $hotspotUser->update([
            'jatuh_tempo' => $newDue,
            'status_akun' => 'enable',
            'status_bayar' => 'belum_bayar',
        ]);

        app(HotspotRadiusSynchronizer::class)->syncSingleUser($hotspotUser);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Layanan hotspot diperpanjang.']);
        }

        return redirect()->route('hotspot-users.index')->with('status', 'Layanan hotspot diperpanjang.');
    }

    public function toggleStatus(HotspotUser $hotspotUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $hotspotUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $newStatus = $hotspotUser->status_akun === 'enable' ? 'disable' : 'enable';
        $hotspotUser->update(['status_akun' => $newStatus]);

        app(HotspotRadiusSynchronizer::class)->syncSingleUser($hotspotUser);

        return response()->json(['status' => $newStatus]);
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            HotspotUser::query()->whereIn('id', $ids)->accessibleBy($currentUser)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'User Hotspot terpilih dihapus.']);
        }

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        // Auto-generate customer_id jika kosong
        if (empty($data['customer_id'])) {
            $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : null;
            $data['customer_id'] = HotspotUser::generateCustomerId($ownerId);
        }

        if (($data['metode_login'] ?? '') === 'username_equals_password') {
            $data['hotspot_password'] = $data['username'] ?? $data['hotspot_password'] ?? null;
        }

        if (! empty($data['nomor_hp'])) {
            $data['nomor_hp'] = $this->normalizePhone($data['nomor_hp']);
        }

        if (! isset($data['biaya_instalasi'])) {
            $data['biaya_instalasi'] = 0;
        }

        if (isset($data['jatuh_tempo']) && $data['jatuh_tempo']) {
            $data['jatuh_tempo'] = Carbon::parse($data['jatuh_tempo'])->endOfDay();
        }

        return $data;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    private function enforceOverdueAction(HotspotUser $user): void
    {
        if (! $user->jatuh_tempo) {
            return;
        }

        $due = Carbon::parse($user->jatuh_tempo)->endOfDay();
        if (now()->greaterThan($due) && $user->aksi_jatuh_tempo === 'isolir' && $user->status_akun !== 'isolir') {
            $user->update(['status_akun' => 'isolir']);
        }
    }
}
