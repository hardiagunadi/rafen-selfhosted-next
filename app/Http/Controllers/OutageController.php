<?php

namespace App\Http\Controllers;

use App\Jobs\SendOutageWaBlastJob;
use App\Models\MikrotikConnection;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\OutageAffectedArea;
use App\Models\OutageUpdate;
use App\Models\ProfileGroup;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\WaGatewayService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OutageController extends Controller
{
    use LogsActivity;

    private const MANAGE_ROLES = ['administrator', 'noc', 'it_support'];
    private const UPDATE_ROLES = ['administrator', 'noc', 'it_support', 'cs', 'teknisi'];

    private function authorizeManage(): User
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, self::MANAGE_ROLES, true)) {
            abort(403);
        }

        return $user;
    }

    private function authorizeUpdate(): User
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! in_array($user->role, self::UPDATE_ROLES, true)) {
            abort(403);
        }

        return $user;
    }

    /**
     * Resolve PPP usernames yang terdaftar pada NAS tertentu.
     * Prioritas: via ProfileGroup.mikrotik_connection_id; fallback via radacct.nasipaddress.
     *
     * @param  int[]  $nasIds
     * @return string[]
     */
    private function resolveNasUsernames(array $nasIds): array
    {
        if (empty($nasIds)) {
            return [];
        }

        // Coba via ProfileGroup terlebih dahulu
        $profileGroupIds = ProfileGroup::whereIn('mikrotik_connection_id', $nasIds)->pluck('id')->all();
        if (! empty($profileGroupIds)) {
            return \App\Models\PppUser::whereIn('profile_group_id', $profileGroupIds)
                ->whereNotNull('username')
                ->pluck('username')
                ->all();
        }

        // Fallback: cari via radacct.nasipaddress = MikrotikConnection.host
        $hosts = MikrotikConnection::whereIn('id', $nasIds)->pluck('host')->all();
        if (empty($hosts)) {
            return [];
        }

        return DB::table('radacct')
            ->whereIn('nasipaddress', $hosts)
            ->distinct()
            ->pluck('username')
            ->all();
    }

    public function index(Request $request)
    {
        $user = $this->authorizeUpdate();

        return view('outages.index', compact('user'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = $this->authorizeUpdate();

        $query = Outage::query()
            ->accessibleBy($user)
            ->withCount('affectedAreas')
            ->with(['assignedTeknisi:id,name,nickname', 'affectedAreas'])
            ->orderByDesc('started_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $outages = $query->paginate(25);

        $data = $outages->getCollection()->map(fn (Outage $o) => [
            'id'                   => $o->id,
            'title'                => $o->title,
            'status'               => $o->status,
            'severity'             => $o->severity,
            'affected_area_count'  => $o->affected_areas_count,
            'started_at'           => $o->started_at?->format('d/m/Y H:i'),
            'estimated_resolved_at'=> $o->estimated_resolved_at?->format('d/m/Y H:i'),
            'resolved_at'          => $o->resolved_at?->format('d/m/Y H:i'),
            'assigned_teknisi'     => $o->assignedTeknisi
                ? ($o->assignedTeknisi->nickname ?? $o->assignedTeknisi->name)
                : '-',
            'wa_blast_count'       => $o->wa_blast_count,
            'affected_users_count' => $o->wa_blast_sent_at ? $o->affectedPppUsers()->count() : null,
            'show_url'             => route('outages.show', $o),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $outages->currentPage(),
            'last_page'    => $outages->lastPage(),
            'total'        => $outages->total(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $this->authorizeManage();

        $teknisiList = User::query()
            ->where('parent_id', $user->effectiveOwnerId())
            ->where('role', 'teknisi')
            ->orderBy('name')
            ->get(['id', 'name', 'nickname']);

        $tenantSettings = TenantSettings::where('user_id', $user->effectiveOwnerId())->first();
        $testBlastPhone = $tenantSettings?->business_phone ?? '';

        $nasConnections = MikrotikConnection::query()->accessibleBy($user)->orderBy('name')->get(['id', 'name', 'host']);

        return view('outages.create', compact('user', 'teknisiList', 'testBlastPhone', 'nasConnections'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->authorizeManage();

        $data = $request->validate([
            'title'                  => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'severity'               => ['required', 'in:low,medium,high,critical'],
            'started_at'             => ['required', 'date'],
            'estimated_resolved_at'  => ['nullable', 'date', 'after:started_at'],
            'assigned_teknisi_id'    => ['nullable', 'integer', 'exists:users,id'],
            'odp_ids'                => ['nullable', 'array'],
            'odp_ids.*'              => ['integer', 'exists:odps,id'],
            'nas_ids'                => ['nullable', 'array'],
            'nas_ids.*'              => ['integer', 'exists:mikrotik_connections,id'],
            'custom_areas'           => ['nullable', 'array'],
            'custom_areas.*'         => ['string', 'max:100'],
            'send_wa_blast'          => ['nullable', 'boolean'],
            'include_status_link'    => ['nullable', 'boolean'],
        ]);

        $ownerId = $user->effectiveOwnerId();

        $outage = DB::transaction(function () use ($data, $ownerId, $user) {
            $outage = Outage::create([
                'owner_id'               => $ownerId,
                'title'                  => $data['title'],
                'description'            => $data['description'] ?? null,
                'status'                 => Outage::STATUS_OPEN,
                'severity'               => $data['severity'],
                'started_at'             => $data['started_at'],
                'estimated_resolved_at'  => $data['estimated_resolved_at'] ?? null,
                'assigned_teknisi_id'    => $data['assigned_teknisi_id'] ?? null,
                'created_by_id'          => $user->id,
                'include_status_link'    => $data['include_status_link'] ?? true,
            ]);

            // Simpan NAS/Router terpilih
            foreach ($data['nas_ids'] ?? [] as $nasId) {
                OutageAffectedArea::create([
                    'outage_id' => $outage->id,
                    'area_type' => 'nas',
                    'nas_id'    => $nasId,
                ]);
            }

            // Simpan ODP terpilih
            foreach ($data['odp_ids'] ?? [] as $odpId) {
                OutageAffectedArea::create([
                    'outage_id' => $outage->id,
                    'area_type' => 'odp',
                    'odp_id'    => $odpId,
                ]);
            }

            // Simpan keyword wilayah
            foreach ($data['custom_areas'] ?? [] as $kw) {
                $kw = trim($kw);
                if ($kw !== '') {
                    OutageAffectedArea::create([
                        'outage_id' => $outage->id,
                        'area_type' => 'keyword',
                        'label'     => $kw,
                    ]);
                }
            }

            OutageUpdate::create([
                'outage_id' => $outage->id,
                'user_id'   => $user->id,
                'type'      => 'created',
                'body'      => 'Insiden gangguan dibuat oleh '.($user->nickname ?? $user->name).'.',
                'is_public' => true,
            ]);

            if (! empty($data['assigned_teknisi_id'])) {
                $teknisi = User::find($data['assigned_teknisi_id']);
                OutageUpdate::create([
                    'outage_id' => $outage->id,
                    'user_id'   => $user->id,
                    'type'      => 'assigned',
                    'body'      => null,
                    'meta'      => 'Teknisi ditugaskan: '.($teknisi ? ($teknisi->nickname ?? $teknisi->name) : $data['assigned_teknisi_id']),
                    'is_public' => false,
                ]);
            }

            return $outage;
        });

        $this->logActivity('created', 'Outage', $outage->id, $outage->title, $ownerId);

        if (! empty($data['send_wa_blast'])) {
            dispatch(new SendOutageWaBlastJob($outage->id, $ownerId, 'initial', $user->id));
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success'   => true,
                'outage_id' => $outage->id,
                'show_url'  => route('outages.show', $outage),
            ]);
        }

        return redirect()->route('outages.show', $outage)
            ->with('success', 'Insiden gangguan berhasil dibuat.');
    }

    public function show(Outage $outage)
    {
        $user = $this->authorizeUpdate();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->isTeknisi() && $outage->assigned_teknisi_id !== $user->id) {
            abort(403);
        }

        $outage->load([
            'affectedAreas.odp:id,name,area',
            'updates.user:id,name,nickname,role',
            'assignedTeknisi:id,name,nickname',
            'createdBy:id,name',
        ]);

        $affectedCount = $outage->affectedPppUsers()->count();

        $canManage = $user->isSuperAdmin() || in_array($user->role, self::MANAGE_ROLES, true);

        return view('outages.show', compact('outage', 'user', 'affectedCount', 'canManage'));
    }

    public function edit(Outage $outage)
    {
        $user = $this->authorizeManage();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $teknisiList = User::query()
            ->where('parent_id', $user->effectiveOwnerId())
            ->where('role', 'teknisi')
            ->orderBy('name')
            ->get(['id', 'name', 'nickname']);

        $outage->load('affectedAreas.odp:id,name,area');

        return view('outages.edit', compact('outage', 'user', 'teknisiList'));
    }

    public function update(Request $request, Outage $outage): JsonResponse
    {
        $user = $this->authorizeManage();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'title'                 => ['nullable', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'severity'              => ['nullable', 'in:low,medium,high,critical'],
            'started_at'            => ['nullable', 'date'],
            'estimated_resolved_at' => ['nullable', 'date'],
        ]);

        $outage->update(array_filter($data, fn ($v) => $v !== null));

        $this->logActivity('updated', 'Outage', $outage->id, $outage->title, $outage->owner_id);

        return response()->json(['success' => true]);
    }

    public function destroy(Outage $outage): JsonResponse
    {
        $user = $this->authorizeManage();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'Outage', $outage->id, $outage->title, $outage->owner_id);
        $outage->delete();

        return response()->json(['success' => true]);
    }

    public function addUpdate(Request $request, Outage $outage): JsonResponse
    {
        $user = $this->authorizeUpdate();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->isTeknisi() && $outage->assigned_teknisi_id !== $user->id) {
            abort(403);
        }

        $canChangeStatus = $user->isSuperAdmin() || in_array($user->role, self::MANAGE_ROLES, true);

        $data = $request->validate([
            'body'      => ['nullable', 'string', 'max:2000'],
            'image'     => ['nullable', 'image', 'max:5120'],
            'is_public' => ['nullable', 'boolean'],
            'status'    => ['nullable', 'in:open,in_progress,resolved'],
        ]);

        if (! $request->filled('body') && ! $request->hasFile('image') && empty($data['status'])) {
            return response()->json(['success' => false, 'message' => 'Isi catatan atau ubah status.'], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('outage-updates', 'public');
        }

        $ownerId   = $outage->owner_id;
        $oldStatus = $outage->status;
        $newStatus = $canChangeStatus ? ($data['status'] ?? null) : null;
        $isPublic  = isset($data['is_public']) ? (bool) $data['is_public'] : true;

        DB::transaction(function () use ($data, $outage, $user, $imagePath, $oldStatus, $newStatus, $isPublic) {
            // Progress note
            if (! empty($data['body']) || $imagePath) {
                OutageUpdate::create([
                    'outage_id'  => $outage->id,
                    'user_id'    => $user->id,
                    'type'       => 'note',
                    'body'       => $data['body'] ?? null,
                    'image_path' => $imagePath,
                    'is_public'  => $isPublic,
                ]);
            }

            // Status change
            if ($newStatus && $newStatus !== $oldStatus) {
                $outage->status = $newStatus;
                if ($newStatus === Outage::STATUS_RESOLVED) {
                    $outage->resolved_at = now();
                }
                $outage->save();

                OutageUpdate::create([
                    'outage_id' => $outage->id,
                    'user_id'   => $user->id,
                    'type'      => $newStatus === Outage::STATUS_RESOLVED ? 'resolved' : 'status_change',
                    'meta'      => $oldStatus.' → '.$newStatus,
                    'is_public' => true,
                ]);
            }
        });

        $this->logActivity('updated', 'Outage', $outage->id, $outage->title, $ownerId);

        if ($newStatus === Outage::STATUS_RESOLVED && $oldStatus !== Outage::STATUS_RESOLVED) {
            dispatch(new SendOutageWaBlastJob($outage->id, $ownerId, 'resolved', $user->id));
        }

        $outage->load(['updates' => fn ($q) => $q->orderByDesc('created_at')->limit(1), 'updates.user:id,name,nickname,role']);
        $latest = $outage->updates->first();

        return response()->json([
            'success'    => true,
            'new_status' => $outage->status,
            'update'     => $latest ? $this->formatUpdate($latest) : null,
        ]);
    }

    public function resolve(Request $request, Outage $outage): JsonResponse
    {
        $request->merge(['status' => Outage::STATUS_RESOLVED]);

        return $this->addUpdate($request, $outage);
    }

    public function blast(Request $request, Outage $outage): JsonResponse
    {
        $user = $this->authorizeManage();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        // Guard: minimal 30 menit antar blast (kecuali force=1)
        if (! $request->boolean('force') && $outage->wa_blast_sent_at) {
            $minutesAgo = $outage->wa_blast_sent_at->diffInMinutes(now());
            if ($minutesAgo < 30) {
                $remaining = 30 - $minutesAgo;

                return response()->json([
                    'success' => false,
                    'message' => "WA blast baru dapat dikirim dalam {$remaining} menit lagi. Gunakan force=1 untuk paksa kirim.",
                ], 429);
            }
        }

        $ownerId = $outage->owner_id;
        $count   = $outage->affectedPppUsers()->count();

        dispatch(new SendOutageWaBlastJob($outage->id, $ownerId, 'initial', $user->id));

        $this->logActivity('blast', 'Outage', $outage->id, $outage->title, $ownerId);

        return response()->json([
            'success'    => true,
            'recipients' => $count,
        ]);
    }

    public function affectedUsers(Outage $outage): JsonResponse
    {
        $user = $this->authorizeUpdate();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $count   = $outage->affectedPppUsers()->count();
        $samples = $outage->affectedPppUsers()
            ->limit(5)
            ->get(['customer_name', 'nomor_hp', 'alamat'])
            ->map(fn ($u) => [
                'name'  => $u->customer_name,
                'phone' => $u->nomor_hp,
            ]);

        return response()->json([
            'count'   => $count,
            'samples' => $samples,
        ]);
    }

    public function testBlast(Request $request): JsonResponse
    {
        $user = $this->authorizeManage();

        $request->validate([
            'test_phone'          => ['required', 'string', 'max:20'],
            'odp_ids'             => ['nullable', 'array'],
            'odp_ids.*'           => ['integer'],
            'nas_ids'             => ['nullable', 'array'],
            'nas_ids.*'           => ['integer'],
            'custom_areas'        => ['nullable', 'array'],
            'custom_areas.*'      => ['string', 'max:100'],
            'include_status_link' => ['nullable', 'boolean'],
        ]);

        $settings = TenantSettings::where('user_id', $user->effectiveOwnerId())->first();
        if (! $settings) {
            return response()->json(['success' => false, 'message' => 'Pengaturan tenant tidak ditemukan.'], 422);
        }

        $waService = WaGatewayService::forTenant($settings);
        if (! $waService) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.'], 422);
        }

        // Hitung calon penerima
        $ownerId         = $user->effectiveOwnerId();
        $odpIds          = $request->input('odp_ids', []);
        $nasIds          = $request->input('nas_ids', []);
        $keywords        = array_filter(array_map('trim', $request->input('custom_areas', [])));

        $nasUsernames    = $this->resolveNasUsernames($nasIds);
        $profileGroupIds = ! empty($nasIds)
            ? ProfileGroup::whereIn('mikrotik_connection_id', $nasIds)->pluck('id')->all()
            : [];

        $query = \App\Models\PppUser::query()
            ->where('owner_id', $ownerId)
            ->where('status_akun', 'enable')
            ->whereNotNull('nomor_hp')
            ->where('nomor_hp', '!=', '');

        if (! empty($odpIds) || ! empty($keywords) || ! empty($profileGroupIds) || ! empty($nasUsernames)) {
            $query->where(function ($q) use ($odpIds, $keywords, $profileGroupIds, $nasUsernames) {
                if (! empty($odpIds)) {
                    $q->orWhereIn('odp_id', $odpIds);
                }
                if (! empty($profileGroupIds)) {
                    $q->orWhereIn('profile_group_id', $profileGroupIds);
                }
                if (! empty($nasUsernames)) {
                    $q->orWhereIn('username', $nasUsernames);
                }
                foreach ($keywords as $kw) {
                    $q->orWhere('alamat', 'LIKE', '%'.$kw.'%');
                }
            });
        }

        $recipientCount = $query->count();

        // Bangun pesan test
        $areaLabels = implode(', ', array_merge(
            array_values($keywords),
        ));
        if (empty($areaLabels)) {
            $areaLabels = '(area belum dipilih)';
        }

        $includeLink = $request->boolean('include_status_link', true);
        $linkLine = $includeLink
            ? "\n\nPantau status perbaikan di:\n".url('/status/preview')
            : '';

        $message = "🧪 *[TEST] Informasi Gangguan Jaringan Internet*\n\n"
            ."Area terdampak: {$areaLabels}\n"
            ."Mulai: ".now()->format('d/m/Y H:i')
            .$linkLine."\n\n"
            ."Mohon maaf atas ketidaknyamanannya. 🙏\n\n"
            ."_(Ini adalah pesan uji coba — bukan gangguan sesungguhnya)_";

        $testPhone = $request->input('test_phone');
        $sent = $waService->sendMessage($testPhone, $message, [
            'event' => 'outage_test_blast',
            'name'  => $user->name,
        ]);

        return response()->json([
            'success'         => $sent,
            'message'         => $sent ? 'Pesan test berhasil dikirim ke '.$testPhone : 'Gagal mengirim pesan test.',
            'recipient_count' => $recipientCount,
        ]);
    }

    public function affectedUsersPreview(Request $request): JsonResponse
    {
        $user = $this->authorizeManage();

        $request->validate([
            'odp_ids'        => ['nullable', 'array'],
            'odp_ids.*'      => ['integer', 'exists:odps,id'],
            'nas_ids'        => ['nullable', 'array'],
            'nas_ids.*'      => ['integer', 'exists:mikrotik_connections,id'],
            'custom_areas'   => ['nullable', 'array'],
            'custom_areas.*' => ['string', 'max:100'],
        ]);

        $ownerId         = $user->effectiveOwnerId();
        $odpIds          = $request->input('odp_ids', []);
        $nasIds          = $request->input('nas_ids', []);
        $keywords        = array_filter(array_map('trim', $request->input('custom_areas', [])));

        $nasUsernames    = $this->resolveNasUsernames($nasIds);
        $profileGroupIds = ! empty($nasIds)
            ? ProfileGroup::whereIn('mikrotik_connection_id', $nasIds)->pluck('id')->all()
            : [];

        if (empty($odpIds) && empty($keywords) && empty($profileGroupIds) && empty($nasUsernames)) {
            return response()->json(['count' => 0, 'samples' => []]);
        }

        $query = \App\Models\PppUser::query()
            ->where('owner_id', $ownerId)
            ->where('status_akun', 'enable')
            ->whereNotNull('nomor_hp')
            ->where('nomor_hp', '!=', '')
            ->where(function ($q) use ($odpIds, $keywords, $profileGroupIds, $nasUsernames) {
                if (! empty($odpIds)) {
                    $q->orWhereIn('odp_id', $odpIds);
                }
                if (! empty($profileGroupIds)) {
                    $q->orWhereIn('profile_group_id', $profileGroupIds);
                }
                if (! empty($nasUsernames)) {
                    $q->orWhereIn('username', $nasUsernames);
                }
                foreach ($keywords as $kw) {
                    $q->orWhere('alamat', 'LIKE', '%'.$kw.'%');
                }
            });

        $count   = $query->count();
        $samples = $query->limit(5)->get(['customer_name', 'nomor_hp'])
            ->map(fn ($u) => ['name' => $u->customer_name, 'phone' => $u->nomor_hp]);

        return response()->json(['count' => $count, 'samples' => $samples]);
    }

    public function assign(Request $request, Outage $outage): JsonResponse
    {
        $user = $this->authorizeManage();

        if (! $user->isSuperAdmin() && $outage->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'assigned_teknisi_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $teknisi = User::findOrFail($data['assigned_teknisi_id']);

        $outage->update(['assigned_teknisi_id' => $teknisi->id]);

        OutageUpdate::create([
            'outage_id' => $outage->id,
            'user_id'   => $user->id,
            'type'      => 'assigned',
            'meta'      => 'Teknisi ditugaskan: '.($teknisi->nickname ?? $teknisi->name),
            'is_public' => false,
        ]);

        // Notify teknisi via WA
        try {
            $settings = TenantSettings::where('user_id', $outage->owner_id)->first();
            if ($settings && $settings->hasWaConfigured() && $teknisi->phone) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $msg = "Halo {$teknisi->name},\n\nAnda ditugaskan untuk menangani gangguan jaringan:\n\n*{$outage->title}*\nSeverity: ".strtoupper($outage->severity)."\nMulai: ".$outage->started_at->format('d/m/Y H:i')."\n\nSegera ditindaklanjuti. Terima kasih.";
                    $service->sendMessage($teknisi->phone, $msg, ['event' => 'outage_assigned']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        $this->logActivity('assigned', 'Outage', $outage->id, $outage->title, $outage->owner_id);

        return response()->json(['success' => true]);
    }

    private function formatUpdate(OutageUpdate $u): array
    {
        return [
            'id'         => $u->id,
            'type'       => $u->type,
            'body'       => $u->body,
            'meta'       => $u->meta,
            'is_public'  => $u->is_public,
            'image_url'  => $u->image_path ? asset('storage/'.$u->image_path) : null,
            'user_name'  => $u->user ? ($u->user->nickname ?? $u->user->name) : 'Sistem',
            'user_role'  => $u->user?->role,
            'created_at' => $u->created_at?->format('d/m/Y H:i'),
        ];
    }
}
