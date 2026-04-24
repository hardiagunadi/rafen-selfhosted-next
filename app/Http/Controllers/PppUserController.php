<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppUserRequest;
use App\Http\Requests\StoreServiceNoteRequest;
use App\Http\Requests\UpdatePppUserRequest;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\MikrotikConnection;
use App\Models\Odp;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\RadiusAccount;
use App\Models\ServiceNote;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\GenieAcsClient;
use App\Services\IsolirSynchronizer;
use App\Services\MikrotikApiClient;
use App\Services\RadiusReplySynchronizer;
use App\Services\WaNotificationService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PppUserController extends Controller
{
    use LogsActivity;

    public function generateCustomerId(Request $request): JsonResponse
    {
        $ownerId = $request->input('owner_id') ? (int) $request->input('owner_id') : $request->user()->effectiveOwnerId();

        return response()->json(['customer_id' => PppUser::generateCustomerId($ownerId)]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = $request->input('search.value', '');
        $filterIsolir = $request->input('filter_isolir', '');
        $filterTagihan = $request->input('filter_tagihan', '');
        $filterOnProcess = $request->input('filter_on_process', '');

        $query = PppUser::query()
            ->with(['owner', 'profile', 'assignedTeknisi', 'invoices' => fn ($q) => $q->where('status', 'unpaid')->latest()->limit(1)])
            ->accessibleBy($currentUser);

        if ($filterIsolir === '1') {
            $query->where('status_akun', 'isolir');
        }

        if ($filterTagihan === '1') {
            $query->whereHas('invoices', fn ($q) => $q->overdue());
        }

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

        $this->applyDatatableOrdering($query, $request);

        $users = $query->skip($start)->take($length > 0 ? $length : 10)->get();

        // Fetch active sessions for this batch of users in one query
        $usernames = $users->pluck('username')->filter()->values()->all();
        $activeSessions = RadiusAccount::whereIn('username', $usernames)
            ->where('is_active', true)
            ->get()
            ->keyBy('username');

        $currentUserForMap = $currentUser;
        $data = $users->map(function (PppUser $user) use ($activeSessions, $currentUserForMap) {
            $invoice = $user->invoices->first();
            $session = $activeSessions->get($user->username);

            $statusBadge = '';
            if ($user->status_registrasi) {
                $registrasiLabels = ['aktif' => 'AKTIF', 'on_process' => 'ON PROCESS', 'non_aktif' => 'NON AKTIF'];
                $registrasiLabel = $registrasiLabels[$user->status_registrasi] ?? strtoupper($user->status_registrasi);
                $statusBadge = '<span class="badge badge-success mr-1">'.$registrasiLabel.'</span>';
            }
            $tipe = $statusBadge.strtoupper(str_replace('_', '/', (string) $user->tipe_service));

            $ip = $user->tipe_ip === 'static' ? ($user->ip_static ?? '-') : 'Automatic';

            $updated = $user->updated_at?->format('Y-m-d H:i') ?? '-';
            $due = $user->jatuh_tempo
                ? '<a href="#" class="text-primary font-weight-bold">'.$user->jatuh_tempo->format('Y-m-d H:i').'</a>'
                : '<span class="text-muted">-</span>';

            $isUnpaid = $invoice && $invoice->status === 'unpaid';
            $hasPending = $invoice ? (($invoice->pending_count ?? 0) > 0) : false;
            $isRenewedWithoutPayment = $isUnpaid && (bool) $invoice->renewed_without_payment;
            $canRenew = $isUnpaid && ! $isRenewedWithoutPayment;
            $canPay = $isUnpaid && ! $hasPending && ! $isRenewedWithoutPayment;
            $canMarkPaid = $isUnpaid && ! $hasPending && $isRenewedWithoutPayment;

            $renewUrl = $invoice ? route('invoices.renew', $invoice) : '';
            $payUrl = $invoice ? route('invoices.pay', $invoice) : '';
            $invoiceNumber = $invoice ? ($invoice->invoice_number ?? '') : '';
            $customerName = $invoice ? ($invoice->customer_name ?? '') : '';
            $total = $invoice ? (int) $invoice->total : 0;

            $renewBtn = $canRenew
                ? '<button class="btn btn-primary btn-sm" data-ajax-post="'.$renewUrl.'" data-confirm="Perpanjang layanan tanpa pembayaran?" title="Perpanjang Layanan"><i class="fas fa-bolt"></i> Renew</button>'
                : '<button class="btn btn-light btn-sm" disabled><i class="fas fa-bolt"></i> Renew</button>';

            if ($hasPending) {
                $bayarBtn = '<a href="'.route('payments.pending').'" class="btn btn-warning btn-sm" title="Menunggu konfirmasi bukti transfer"><i class="fas fa-clock"></i> Bayar</a>';
            } elseif ($canMarkPaid || $canPay) {
                $bayarBtn = '<button class="btn btn-success btn-sm"'
                    .' data-pay-modal="1"'
                    .' data-pay-url="'.e($payUrl).'"'
                    .' data-invoice-number="'.e($invoiceNumber).'"'
                    .' data-customer-name="'.e($customerName).'"'
                    .' data-total="'.$total.'"'
                    .' title="Bayar"><i class="fas fa-check"></i> Bayar</button>';
            } else {
                $bayarBtn = '<button class="btn btn-light btn-sm" disabled><i class="fas fa-check"></i> Bayar</button>';
            }

            $invoiceMenuItem = $invoice
                ? '<button class="dropdown-item text-danger" data-ajax-delete="'.route('invoices.destroy', $invoice).'" data-confirm="Hapus tagihan ini?"><i class="fas fa-file-invoice mr-1"></i>Hapus Tagihan</button>'
                : '<span class="dropdown-item text-muted"><i class="fas fa-file-invoice mr-1"></i>Hapus Tagihan</span>';

            $isTeknisi = $currentUserForMap->isTeknisi();
            $isAssigned = $user->assigned_teknisi_id !== null && $user->assigned_teknisi_id === $currentUserForMap->id;
            $canManage = ! $isTeknisi || is_null($user->assigned_teknisi_id) || $isAssigned;

            if ($canManage) {
                $aksi = '<div class="btn-group btn-group-sm">'.
                    '<a href="'.route('ppp-users.edit', $user).'" class="btn btn-warning text-white" title="Edit"><i class="fas fa-pen"></i></a>'.
                    '<button type="button" class="btn btn-warning dropdown-toggle dropdown-toggle-split text-white" data-toggle="dropdown"></button>'.
                    '<div class="dropdown-menu dropdown-menu-right">'.
                        $invoiceMenuItem.
                        '<div class="dropdown-divider"></div>'.
                        '<button class="dropdown-item text-danger" data-ajax-delete="'.route('ppp-users.destroy', $user).'" data-confirm="Hapus user PPP ini?"><i class="fas fa-user-times mr-1"></i>Hapus User</button>'.
                    '</div>'.
                    '</div>';
            } else {
                $aksi = '<span class="text-muted small">Read-only</span>';
            }

            $teknisiLabel = $user->assignedTeknisi ? '<span class="badge badge-info">'.e($user->assignedTeknisi->name).'</span>' : '<span class="text-muted">-</span>';

            return [
                'checkbox' => '<input type="checkbox" name="ids[]" value="'.$user->id.'">',
                'customer_id' => $canManage
                    ? '<a href="#" class="toggle-status-btn badge badge-'.($user->status_akun === 'enable' ? 'success' : 'danger').'" data-toggle-url="'.route('ppp-users.toggle-status', $user).'" title="Klik untuk '.($user->status_akun === 'enable' ? 'disable' : 'enable').'">'.($user->customer_id ?? '-').'</a>'
                    : '<span class="badge badge-'.($user->status_akun === 'enable' ? 'success' : 'danger').'">'.($user->customer_id ?? '-').'</span>',
                'nama' => (function () use ($user, $session) {
                    if ($session) {
                        $tooltipText = 'CONNECTED | '.$session->caller_id.' | Online: '.$session->uptime;
                    } else {
                        $tooltipText = 'DISCONNECTED';
                    }
                    $tooltipText .= ' — Klik untuk edit';
                    $iconColor = $session ? 'text-success' : 'text-secondary';

                    return '<a href="'.route('ppp-users.edit', $user).'" class="font-weight-bold text-uppercase text-dark">'.$user->customer_name.'</a>'
                        .' <i class="fas fa-info-circle '.$iconColor.'" data-toggle="tooltip" data-placement="top" title="'.e($tooltipText).'"></i>';
                })(),
                'tipe' => $tipe,
                'paket' => $user->profile?->name ?? '-',
                'ip' => $ip,
                'diperpanjang' => $updated,
                'jatuh_tempo' => $due,
                'owner' => $user->owner?->email ?? $user->owner?->name ?? '-',
                'teknisi' => $teknisiLabel,
                'renew_print' => '<div class="btn-group btn-group-sm">'.$renewBtn.$bayarBtn.'</div>',
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

        $users = PppUser::query()
            ->accessibleBy($request->user())
            ->where(function ($query) use ($keyword): void {
                $query->where('customer_name', 'like', "%{$keyword}%")
                    ->orWhere('customer_id', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%");
            })
            ->latest()
            ->limit(8)
            ->get(['id', 'customer_name', 'customer_id', 'username']);

        $data = $users->map(function (PppUser $user): array {
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

    private function applyDatatableOrdering(Builder $query, Request $request): void
    {
        $orderColumnIndex = $request->input('order.0.column');
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc'));
        $orderDirection = in_array($orderDirection, ['asc', 'desc'], true) ? $orderDirection : 'asc';

        $columnMap = [
            'customer_id' => 'customer_id',
            'diperpanjang' => 'updated_at',
            'jatuh_tempo' => 'jatuh_tempo',
        ];

        if (! ctype_digit((string) $orderColumnIndex)) {
            $this->applyDefaultDatatableOrdering($query);

            return;
        }

        $columnKey = data_get($request->input('columns', []), sprintf('%d.data', (int) $orderColumnIndex));
        $databaseColumn = $columnMap[$columnKey] ?? null;

        if ($databaseColumn === null) {
            $this->applyDefaultDatatableOrdering($query);

            return;
        }

        if ($databaseColumn === 'jatuh_tempo') {
            $query->orderByRaw('jatuh_tempo IS NULL ASC')
                ->orderBy($databaseColumn, $orderDirection)
                ->orderByDesc('id');

            return;
        }

        $query->orderBy($databaseColumn, $orderDirection)
            ->orderByDesc('id');
    }

    private function applyDefaultDatatableOrdering(Builder $query): void
    {
        $query->orderByRaw('jatuh_tempo IS NULL ASC')
            ->orderBy('jatuh_tempo')
            ->orderByDesc('id');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $currentUser = $request->user();

        $query = PppUser::query()->with(['owner', 'profileGroup', 'profile', 'invoices' => function ($q) {
            $q->latest();
        }]);

        // Apply tenant data isolation
        $query->accessibleBy($currentUser);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage > 0 ? $perPage : 10)->withQueryString();
        $users->getCollection()->each(function (PppUser $user): void {
            $this->ensureInvoiceWindow($user);
            $this->enforceOverdueAction($user);
        });

        $now = now();
        $stats = [
            'registrasi_bulan_ini' => PppUser::query()->accessibleBy($currentUser)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'renewal_bulan_ini' => PppUser::query()->accessibleBy($currentUser)->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count(),
            'pelanggan_isolir' => PppUser::query()->accessibleBy($currentUser)->where('status_akun', 'isolir')->count(),
            'akun_disable' => PppUser::query()->accessibleBy($currentUser)->where('status_akun', 'disable')->count(),
        ];

        return view('ppp_users.index', compact('users', 'stats', 'perPage', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $currentUser = auth()->user();

        return view('ppp_users.create', [
            'owners' => $currentUser->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$currentUser]),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->accessibleBy($currentUser)->orderBy('name')->get(),
            'odps' => Odp::query()->accessibleBy($currentUser)->orderBy('code')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePppUserRequest $request): RedirectResponse
    {
        $currentUser = $request->user();
        $validated = $request->validated();

        if (! $currentUser->isSuperAdmin()) {
            $validated['owner_id'] = $currentUser->effectiveOwnerId();
        }

        $data = $this->prepareData($validated);
        $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : $currentUser->effectiveOwnerId();
        $owner = User::query()->find($ownerId);

        if ($owner && $owner->hasReachedPppUsersLimit($ownerId)) {
            $limit = $owner->getEffectivePppUsersLimit();

            return back()
                ->withInput()
                ->withErrors([
                    'customer_name' => "Batas PPP Users tenant sudah tercapai ({$limit}). Ubah limit lisensi/paket terlebih dahulu.",
                ]);
        }

        $user = PppUser::create($data);

        if ($data['status_registrasi'] === 'on_process') {
            // ON PROCESS: buat invoice (jika belum bayar) tapi tahan WA registrasi
            $invoice = null;
            if ($data['status_bayar'] === 'belum_bayar') {
                $invoice = $this->createInvoiceForUser($user, null, false, true);
            }
            $settings = TenantSettings::getOrCreate((int) $user->owner_id);
            WaNotificationService::notifyOnProcess($settings, $user->load('profile'), $invoice);
        } else {
            if ($data['status_bayar'] === 'belum_bayar') {
                $this->createInvoiceForUser($user, null, false, true);
            }
            $settings = TenantSettings::getOrCreate((int) $user->owner_id);
            WaNotificationService::notifyRegistration($settings, $user->load('profile'));
        }

        app(RadiusReplySynchronizer::class)->syncSingleUser($user);

        if ($user->status_akun === 'isolir') {
            app(IsolirSynchronizer::class)->isolate($user);
        }

        $this->logActivity('created', 'PppUser', $user->id, $user->customer_name, (int) $user->owner_id);

        return redirect()->route('ppp-users.index')->with('status', 'User PPP ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PppUser $pppUser): RedirectResponse
    {
        return redirect()->route('ppp-users.edit', $pppUser);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PppUser $pppUser): View
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
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

        return view('ppp_users.edit', [
            'pppUser' => $pppUser,
            'owners' => $currentUser->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$currentUser]),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->accessibleBy($currentUser)->orderBy('name')->get(),
            'odps' => Odp::query()->accessibleBy($currentUser)->orderBy('code')->get(),
            'teknisiList' => $teknisiList,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePppUserRequest $request, PppUser $pppUser): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $originalStatus = $pppUser->status_bayar;
        $originalStatusAkun = $pppUser->status_akun;
        $originalStatusRegistrasi = $pppUser->status_registrasi;
        $originalDue = $pppUser->jatuh_tempo;
        $originalProfileId = $pppUser->ppp_profile_id;
        $originalProfileGroupId = $pppUser->profile_group_id;
        $originalUsername = $pppUser->username;
        $originalPassword = $pppUser->ppp_password;
        $validated = $request->validated();

        if (! $currentUser->isSuperAdmin()) {
            $validated['owner_id'] = $currentUser->effectiveOwnerId();
        }

        $data = $this->prepareData($validated, $pppUser);

        $pppUser->update($data);

        $dueDateChanged = $this->dueDateChanged($originalDue, $pppUser->jatuh_tempo);

        // ON PROCESS → AKTIF: trigger invoice + WA registrasi
        if ($originalStatusRegistrasi === 'on_process' && $pppUser->status_registrasi === 'aktif') {
            if ($pppUser->status_bayar === 'belum_bayar' && ! $pppUser->invoices()->exists()) {
                $this->createInvoiceForUser($pppUser, null, false, true);
            }
            $settings = TenantSettings::getOrCreate((int) $pppUser->owner_id);
            WaNotificationService::notifyRegistration($settings, $pppUser->load('profile'));
        }

        if ($data['status_bayar'] === 'belum_bayar' && $originalStatus !== 'belum_bayar') {
            $this->createInvoiceForUser($pppUser);
        }

        if ($data['status_bayar'] === 'belum_bayar' && $dueDateChanged) {
            $this->syncUnpaidInvoiceDueDateAfterManualChange($pppUser, $originalDue);
        }

        if ($data['status_bayar'] === 'sudah_bayar' && $originalStatus !== 'sudah_bayar') {
            $this->markInvoicePaid($pppUser);
        }

        if ($dueDateChanged) {
            $this->applyManualDueDateChangeEffects($pppUser, $originalDue, $originalStatusAkun);
        }

        app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

        if ($pppUser->status_akun === 'isolir' && $originalStatusAkun !== 'isolir') {
            // Baru masuk isolir: setup radreply isolir + kick sesi aktif
            app(IsolirSynchronizer::class)->isolate($pppUser);
        } elseif ($pppUser->status_akun !== 'isolir' && $originalStatusAkun === 'isolir') {
            // Keluar dari isolir: kick sesi isolir agar reconnect normal
            app(IsolirSynchronizer::class)->deisolate($pppUser);
        } elseif (
            $pppUser->status_akun === 'enable' &&
            ($pppUser->ppp_profile_id !== $originalProfileId || $pppUser->profile_group_id !== $originalProfileGroupId)
        ) {
            // Profil/paket berubah: kick sesi aktif agar reconnect dengan atribut RADIUS baru (pool IP, rate limit)
            $connections = MikrotikConnection::query()
                ->accessibleBy(auth()->user())
                ->get();
            foreach ($connections as $conn) {
                try {
                    $client = app(MikrotikApiClient::class, ['connection' => $conn]);
                    $client->connect();
                    $active = $client->command('/ppp/active/print', [], ['name' => $pppUser->username]);
                    foreach ($active['data'] ?? [] as $session) {
                        if (isset($session['.id'])) {
                            $client->command('/ppp/active/remove', ['=.id' => $session['.id']]);
                        }
                    }
                    $client->disconnect();
                } catch (\Throwable) {
                    // skip unreachable routers
                }
            }
        }

        // Auto-push PPPoE credentials to modem via GenieACS jika ada perubahan username/password
        $usernameChanged = ($data['username'] ?? $originalUsername) !== $originalUsername;
        $passwordChanged = ($data['ppp_password'] ?? $originalPassword) !== $originalPassword;
        if (($usernameChanged || $passwordChanged) && $pppUser->cpeDevice) {
            $device = $pppUser->cpeDevice;
            try {
                app(GenieAcsClient::class)->setPppoeCredentials(
                    $device->genieacs_device_id,
                    $pppUser->username,
                    $pppUser->ppp_password,
                    $device->param_profile ?? 'igd'
                );
            } catch (\Throwable) {
                // Jangan gagalkan update jika GenieACS tidak tersedia
            }
        }

        $this->logActivity('updated', 'PppUser', $pppUser->id, $pppUser->customer_name, (int) $pppUser->owner_id);

        return redirect()->route('ppp-users.index')->with('status', 'User PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppUser $pppUser): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        if ($currentUser->role === 'teknisi') {
            abort(403);
        }

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'PppUser', $pppUser->id, $pppUser->customer_name, (int) $pppUser->owner_id);
        $pppUser->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'User PPP dihapus.']);
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP dihapus.');
    }

    public function toggleStatus(PppUser $pppUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $newStatus = $pppUser->status_akun === 'enable' ? 'disable' : 'enable';
        $pppUser->update(['status_akun' => $newStatus]);

        app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

        return response()->json(['status' => $newStatus]);
    }

    public function invoiceDatatable(Request $request, PppUser $pppUser): JsonResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = $request->input('search.value', '');

        $query = Invoice::query()->where('ppp_user_id', $pppUser->id)->with('owner');

        $total = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('paket_langganan', 'like', "%{$search}%");
            });
        }

        $filtered = (clone $query)->count();

        $orderCol = (int) ($request->input('order.0.column', 0));
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $cols = ['id', 'invoice_number', 'paket_langganan', 'total', 'created_at', 'due_date'];
        $query->orderBy($cols[$orderCol] ?? 'id', $orderDir);

        $invoices = $query->skip($start)->take($length > 0 ? $length : 10)->get();

        $currentDueDate = $pppUser->jatuh_tempo ? Carbon::parse($pppUser->jatuh_tempo)->endOfDay() : null;

        $data = $invoices->map(function (Invoice $invoice) use ($currentDueDate) {
            $statusBadge = $invoice->status === 'paid'
                ? '<span class="badge badge-success">Lunas</span>'
                : '<span class="badge badge-warning">Belum Bayar</span>';
            $contextBadge = '';

            if ($invoice->isHistoricalUnpaid($currentDueDate)) {
                $contextBadge = ' <span class="badge badge-secondary">Invoice Tunggakan</span>';
            } elseif ($invoice->isCurrentBillingInvoice($currentDueDate)) {
                $contextBadge = ' <span class="badge badge-info">Perpanjangan Bulan Berjalan</span>';
            }

            $aksi = '<div class="btn-group btn-group-sm">'
                .'<a href="'.route('invoices.show', $invoice).'" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>'
                .'<a href="'.route('invoices.nota', $invoice).'" target="_blank" class="btn btn-secondary btn-sm" title="Nota"><i class="fas fa-print"></i></a>'
                .'</div>';

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number.' '.$statusBadge.$contextBadge,
                'paket_langganan' => $invoice->paket_langganan ?? '-',
                'total' => 'Rp '.number_format((float) $invoice->total, 0, ',', '.'),
                'created_at' => $invoice->created_at?->format('M d, Y') ?? '-',
                'due_date' => $invoice->due_date?->format('M d, Y') ?? '-',
                'owner' => $invoice->owner?->name ?? '-',
                'aksi' => $aksi,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function dialupDatatable(Request $request, PppUser $pppUser): JsonResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = $request->input('search.value', '');

        $query = DB::table('radacct')
            ->where('username', $pppUser->username)
            ->orderBy('radacctid', 'desc')
            ->limit(100);

        $total = DB::table('radacct')->where('username', $pppUser->username)->count();
        $total = min($total, 100);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nasipaddress', 'like', "%{$search}%")
                    ->orWhere('acctterminatecause', 'like', "%{$search}%")
                    ->orWhere('callingstationid', 'like', "%{$search}%");
            });
        }

        $filtered = (clone $query)->count();

        $rows = $query->skip($start)->take($length > 0 ? $length : 10)->get();

        $data = $rows->map(function ($row) {
            $uploadBytes = (int) ($row->acctinputoctets ?? 0);
            $downloadBytes = (int) ($row->acctoutputoctets ?? 0);

            $formatBytes = function (int $bytes): string {
                if ($bytes >= 1073741824) {
                    return round($bytes / 1073741824, 2).' GB';
                }
                if ($bytes >= 1048576) {
                    return round($bytes / 1048576, 2).' MB';
                }
                if ($bytes >= 1024) {
                    return round($bytes / 1024, 2).' KB';
                }

                return $bytes.' B';
            };

            $upSecs = (int) ($row->acctsessiontime ?? 0);
            $uptime = sprintf('%dh %dm %ds', intdiv($upSecs, 3600), intdiv($upSecs % 3600, 60), $upSecs % 60);

            return [
                'radacctid' => $row->radacctid,
                'uptime' => $uptime,
                'start' => $row->acctstarttime ? Carbon::parse($row->acctstarttime)->format('M/d/Y H:i') : '-',
                'stop' => $row->acctstoptime ? Carbon::parse($row->acctstoptime)->format('M/d/Y H:i') : '-',
                'nas' => $row->calledstationid ?: $row->nasipaddress,
                'upload' => '<i class="fas fa-upload text-success mr-1"></i>'.$formatBytes($uploadBytes),
                'download' => '<i class="fas fa-download text-info mr-1"></i>'.$formatBytes($downloadBytes),
                'terminate' => '<em>'.($row->acctterminatecause ?: '-').'</em>',
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function addInvoice(PppUser $pppUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $invoice = $this->createInvoiceForUser(
            $pppUser,
            $this->resolveManualInvoiceDueDate($pppUser),
            true
        );

        if (! $invoice) {
            return response()->json([
                'error' => 'Tagihan tidak dapat ditambahkan karena pelanggan belum memiliki profil paket aktif.',
            ], 422);
        }

        return response()->json([
            'status' => 'Tagihan berhasil ditambahkan.',
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date?->toDateString(),
        ]);
    }

    public function disconnect(PppUser $pppUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        try {
            $pppUser->load('owner');
            $connections = MikrotikConnection::query()
                ->accessibleBy($currentUser)
                ->get();

            foreach ($connections as $conn) {
                try {
                    $client = app(MikrotikApiClient::class, ['connection' => $conn]);
                    $client->connect();
                    $active = $client->command('/ppp/active/print', [], ['name' => $pppUser->username]);
                    foreach ($active['data'] ?? [] as $session) {
                        if (isset($session['.id'])) {
                            $client->command('/ppp/active/remove', ['=.id' => $session['.id']]);
                        }
                    }
                    $client->disconnect();
                } catch (\Throwable) {
                    // skip unreachable routers
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['status' => 'Gagal memutus koneksi: '.$e->getMessage()], 500);
        }

        return response()->json(['status' => 'Koneksi berhasil diputus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            $query = PppUser::query()->whereIn('id', $ids)->accessibleBy($currentUser);
            $query->each(function (PppUser $u): void {
                $this->logActivity('deleted', 'PppUser', $u->id, $u->customer_name, (int) $u->owner_id);
            });
            $query->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'User PPP terpilih dihapus.']);
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP terpilih dihapus.');
    }

    public function notaAktivasi(PppUser $pppUser): View
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $pppUser->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $pppUser->load(['profile', 'owner']);
        $settings = TenantSettings::where('user_id', $pppUser->owner_id)->first();

        return view('ppp-users.nota-aktivasi', compact('pppUser', 'settings'));
    }

    public function notaLayanan(Request $request, PppUser $pppUser): View
    {
        $requestedType = trim((string) $request->query('type', 'aktivasi'));

        return $this->buildNotaLayananView($pppUser, $requestedType);
    }

    public function storeServiceNote(StoreServiceNoteRequest $request, PppUser $pppUser): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $this->buildServiceNoteAttributes(
            user: $user,
            pppUser: $pppUser,
            validated: $request->validated(),
        );

        $serviceNote = ServiceNote::query()->create($attributes);

        $this->logActivity('service_note_created', 'ServiceNote', $serviceNote->id, $serviceNote->document_number, (int) $serviceNote->owner_id, [
            'ppp_user_id' => $pppUser->id,
            'total' => $serviceNote->total,
            'note_type' => $serviceNote->note_type,
            'status' => $serviceNote->status,
        ]);

        return redirect()
            ->route('service-notes.print', $serviceNote)
            ->with(
                'status',
                $serviceNote->payment_method === 'transfer'
                    ? 'Nota layanan transfer berhasil dibuat dan menunggu konfirmasi penerimaan dana.'
                    : 'Nota layanan berhasil disimpan sebagai pendapatan.'
            );
    }

    public function editServiceNote(ServiceNote $serviceNote): View
    {
        /** @var User $user */
        $user = auth()->user();

        $serviceNote->loadMissing(['pppUser.profile', 'pppUser.owner']);

        if (! $serviceNote->isPending()) {
            abort(404);
        }

        $pppUser = $serviceNote->pppUser;

        if (! $pppUser) {
            abort(404);
        }

        $this->authorizeServiceNoteWorkspace($user, $pppUser, $serviceNote);

        return $this->buildNotaLayananView($pppUser, $serviceNote->note_type, $serviceNote);
    }

    public function updateServiceNote(StoreServiceNoteRequest $request, ServiceNote $serviceNote): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $serviceNote->loadMissing(['pppUser.profile']);

        if (! $serviceNote->isPending()) {
            abort(404);
        }

        $pppUser = $serviceNote->pppUser;

        if (! $pppUser) {
            abort(404);
        }

        $attributes = $this->buildServiceNoteAttributes(
            user: $user,
            pppUser: $pppUser,
            validated: $request->validated(),
            existingServiceNote: $serviceNote,
        );

        $serviceNote->update($attributes);

        $this->logActivity('service_note_updated', 'ServiceNote', $serviceNote->id, $serviceNote->document_number, (int) $serviceNote->owner_id, [
            'ppp_user_id' => $pppUser->id,
            'total' => $serviceNote->total,
            'note_type' => $serviceNote->note_type,
            'status' => $serviceNote->status,
        ]);

        return redirect()
            ->route('service-notes.print', $serviceNote)
            ->with(
                'status',
                $serviceNote->payment_method === 'transfer'
                    ? 'Nota layanan transfer berhasil diperbarui dan masih menunggu konfirmasi penerimaan dana.'
                    : 'Nota layanan berhasil diperbarui dan sudah tercatat sebagai pendapatan.'
            );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data, ?PppUser $existing = null): array
    {
        if (array_key_exists('odp_id', $data)) {
            $data['odp_id'] = $data['odp_id'] !== '' && $data['odp_id'] !== null ? (int) $data['odp_id'] : null;
        }

        // Auto-generate customer_id jika kosong
        if (empty($data['customer_id'])) {
            $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : ($existing?->owner_id ? (int) $existing->owner_id : null);
            $data['customer_id'] = PppUser::generateCustomerId($ownerId);
        }

        if (($data['tipe_ip'] ?? '') !== 'static') {
            $data['profile_group_id'] = null;
            $data['ip_static'] = null;
        }

        if (! empty($data['nomor_hp'])) {
            $data['nomor_hp'] = $this->normalizePhone($data['nomor_hp']);
        }

        if (($data['metode_login'] ?? '') === 'username_equals_password') {
            $username = $data['username'] ?? null;

            if (empty($data['ppp_password'])) {
                $data['ppp_password'] = $username;
            }

            if (empty($data['password_clientarea'])) {
                $data['password_clientarea'] = $username;
            }
        }

        $ownerId = $data['owner_id'] ?? $existing?->owner_id;
        if (! empty($data['odp_id'])) {
            $selectedOdp = Odp::query()
                ->whereKey($data['odp_id'])
                ->when($ownerId, fn ($query) => $query->where('owner_id', (int) $ownerId))
                ->first();

            if (! $selectedOdp) {
                throw ValidationException::withMessages([
                    'odp_id' => 'ODP tidak valid untuk owner yang dipilih.',
                ]);
            }

            if (empty($data['odp_pop'])) {
                $data['odp_pop'] = $selectedOdp->code;
            }
        }

        $data['latitude'] = $this->normalizeCoordinate($data['latitude'] ?? null, -90, 90);
        $data['longitude'] = $this->normalizeCoordinate($data['longitude'] ?? null, -180, 180);

        if ($data['latitude'] === null || $data['longitude'] === null) {
            $data['location_accuracy_m'] = null;
            $data['location_capture_method'] = null;
            $data['location_captured_at'] = null;
        } else {
            $accuracy = $data['location_accuracy_m'] ?? null;
            $data['location_accuracy_m'] = is_numeric($accuracy) ? round((float) $accuracy, 2) : null;
            $method = $data['location_capture_method'] ?? null;
            $data['location_capture_method'] = in_array($method, ['gps', 'map_picker', 'manual'], true) ? $method : 'manual';
            $capturedAt = $data['location_captured_at'] ?? null;
            $data['location_captured_at'] = $capturedAt ? Carbon::parse((string) $capturedAt) : now();
        }

        $data['durasi_promo_bulan'] = $data['durasi_promo_bulan'] ?? 0;
        $data['biaya_instalasi'] = $data['biaya_instalasi'] ?? 0;
        $data['jatuh_tempo'] = $this->resolveDueDate($data['jatuh_tempo'] ?? null, $existing, $ownerId ? (int) $ownerId : null);
        $data = $this->assignSqlPoolIp($data, $existing);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function assignSqlPoolIp(array $data, ?PppUser $existing = null): array
    {
        $profileGroupId = $data['profile_group_id'] ?? $existing?->profile_group_id;
        if (! $profileGroupId) {
            return $data;
        }

        $ipType = $data['tipe_ip'] ?? $existing?->tipe_ip;
        if ($ipType !== 'static') {
            return $data;
        }

        $currentIp = $data['ip_static'] ?? $existing?->ip_static;
        if (! empty($currentIp)) {
            return $data;
        }

        $group = ProfileGroup::query()
            ->select('id', 'ip_pool_mode', 'range_start', 'range_end', 'host_min', 'host_max')
            ->find($profileGroupId);

        if (! $group || $group->ip_pool_mode !== 'sql') {
            return $data;
        }

        $nextIp = $this->nextAvailableSqlPoolIp($group, $existing);
        if ($nextIp === null) {
            throw ValidationException::withMessages([
                'ip_static' => 'SQL IP Pool sudah habis atau belum memiliki range IP yang valid.',
            ]);
        }

        $data['ip_static'] = $nextIp;

        return $data;
    }

    private function nextAvailableSqlPoolIp(ProfileGroup $group, ?PppUser $existing = null): ?string
    {
        [$rangeStart, $rangeEnd] = $this->resolvePoolRange($group);
        if (! $rangeStart || ! $rangeEnd) {
            return null;
        }

        $startLong = $this->ipToLong($rangeStart);
        $endLong = $this->ipToLong($rangeEnd);
        if ($startLong === null || $endLong === null || $startLong > $endLong) {
            return null;
        }

        $usedIps = PppUser::query()
            ->where('profile_group_id', $group->id)
            ->whereNotNull('ip_static')
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->pluck('ip_static')
            ->map(fn (string $ip) => $this->ipToLong($ip))
            ->filter(fn (?int $ip) => $ip !== null)
            ->unique()
            ->all();

        $usedLookup = array_fill_keys($usedIps, true);

        for ($current = $startLong; $current <= $endLong; $current++) {
            if (! isset($usedLookup[$current])) {
                return $this->longToIp($current);
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolvePoolRange(ProfileGroup $group): array
    {
        if ($group->range_start && $group->range_end) {
            return [$group->range_start, $group->range_end];
        }

        return [$group->host_min, $group->host_max];
    }

    private function ipToLong(string $ip): ?int
    {
        $long = ip2long($ip);
        if ($long === false) {
            return null;
        }

        return $long < 0 ? $long + (2 ** 32) : $long;
    }

    private function longToIp(int $long): string
    {
        if ($long > 2147483647) {
            $long -= 2 ** 32;
        }

        return long2ip($long);
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

    private function normalizeCoordinate(mixed $value, float $min, float $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $floatValue = (float) $value;
        if ($floatValue < $min || $floatValue > $max) {
            return null;
        }

        return number_format($floatValue, 7, '.', '');
    }

    private function resolveDueDate(?string $input, ?PppUser $existing = null, ?int $ownerId = null): ?Carbon
    {
        if ($input) {
            return Carbon::parse($input)->endOfDay();
        }

        if ($existing) {
            return $existing->jatuh_tempo;
        }

        // Jika tenant punya billing_date, hitung jatuh tempo berdasarkan itu
        if ($ownerId) {
            $settings = TenantSettings::getOrCreate($ownerId);
            $billingDay = $settings->billing_date;

            if ($billingDay) {
                $billingDay = max(1, min(28, (int) $billingDay));
                $candidate = now()->startOfDay()->setDay($billingDay)->endOfDay();

                // Jika kandidat sudah sama dengan atau sebelum hari ini, pakai bulan depan
                if ($candidate->lte(now())) {
                    $candidate = $candidate->addMonthNoOverflow();
                }

                return $candidate;
            }
        }

        return now()->addMonthNoOverflow()->endOfDay();
    }

    private function dueDateChanged(null|Carbon|string $originalDue, null|Carbon|string $currentDue): bool
    {
        $original = $originalDue ? Carbon::parse($originalDue)->endOfDay() : null;
        $current = $currentDue ? Carbon::parse($currentDue)->endOfDay() : null;

        if ($original === null || $current === null) {
            return $original?->toDateTimeString() !== $current?->toDateTimeString();
        }

        return ! $original->equalTo($current);
    }

    private function applyManualDueDateChangeEffects(PppUser $user, null|Carbon|string $originalDue, string $originalStatusAkun): void
    {
        if (
            $user->status_bayar !== 'belum_bayar'
            || $user->aksi_jatuh_tempo !== 'isolir'
            || ! $user->jatuh_tempo
        ) {
            return;
        }

        $settings = TenantSettings::getOrCreate((int) $user->owner_id);
        if (! $settings->auto_isolate_unpaid) {
            return;
        }

        $newDue = Carbon::parse($user->jatuh_tempo)->endOfDay();
        $previousDue = $originalDue ? Carbon::parse($originalDue)->endOfDay() : null;

        if (
            $previousDue
            && $newDue->lt($previousDue)
            && now()->greaterThan($newDue)
            && $user->status_akun !== 'isolir'
        ) {
            $user->update(['status_akun' => 'isolir']);
            $user->refresh();

            return;
        }

        if (
            $previousDue
            && $newDue->gt($previousDue)
            && $originalStatusAkun === 'isolir'
            && $user->status_akun === 'isolir'
            && now()->lte($newDue)
        ) {
            $user->update(['status_akun' => 'enable']);
            $user->refresh();
        }
    }

    private function syncUnpaidInvoiceDueDateAfterManualChange(PppUser $user, null|Carbon|string $originalDue): void
    {
        if (! $user->jatuh_tempo) {
            return;
        }

        $newDueDate = Carbon::parse($user->jatuh_tempo)->endOfDay();
        if ($this->userHasInvoiceForDueDate($user, $newDueDate, 'paid')) {
            return;
        }

        $currentInvoice = $user->invoices()
            ->where('status', 'unpaid')
            ->when(
                $originalDue,
                fn ($query) => $query->whereDate('due_date', Carbon::parse($originalDue)->toDateString()),
                fn ($query) => $query->orderByDesc('due_date')
            )
            ->latest('id')
            ->first();

        if (! $currentInvoice) {
            $this->createInvoiceForUser($user, $newDueDate, true);

            return;
        }

        $currentInvoice->update(['due_date' => $newDueDate]);

        $user->invoices()
            ->where('status', 'unpaid')
            ->whereKeyNot($currentInvoice->id)
            ->whereDate('due_date', $newDueDate->toDateString())
            ->delete();
    }

    private function resolveManualInvoiceDueDate(PppUser $user): Carbon
    {
        $candidate = $user->jatuh_tempo
            ? Carbon::parse($user->jatuh_tempo)->endOfDay()
            : now()->addMonthsNoOverflow($this->resolveInvoiceCycleMonths($user))->endOfDay();
        $cycleMonths = $this->resolveInvoiceCycleMonths($user);

        while ($this->userHasInvoiceForDueDate($user, $candidate, 'paid')) {
            $candidate = $candidate->copy()->addMonthsNoOverflow($cycleMonths)->endOfDay();
        }

        return $candidate;
    }

    private function resolveInvoiceCycleMonths(PppUser $user): int
    {
        return max(1, (int) ($user->profile?->masa_aktif ?? 1));
    }

    private function createInvoiceForUser(PppUser $user, ?Carbon $dueOverride = null, bool $forceNew = false, bool $applyProrata = false): ?Invoice
    {
        $dueDate = $dueOverride
            ? $dueOverride->copy()->endOfDay()
            : ($user->jatuh_tempo ? Carbon::parse($user->jatuh_tempo)->endOfDay() : now()->addMonthNoOverflow()->endOfDay());

        if ($forceNew) {
            if ($this->userHasInvoiceForDueDate($user, $dueDate, 'paid')) {
                return null;
            }

            $user->invoices()
                ->where('status', 'unpaid')
                ->whereDate('due_date', $dueDate->toDateString())
                ->delete();
        } else {
            if ($this->userHasInvoiceForDueDate($user, $dueDate)) {
                return null;
            }
        }

        $profile = $user->profile;
        if (! $profile) {
            return null;
        }

        $promoMonths = (int) ($user->durasi_promo_bulan ?? 0);
        $promoActive = $user->promo_aktif && $promoMonths > 0 && $user->created_at && $user->created_at->diffInMonths(now()) < $promoMonths;
        $hargaAsli = $promoActive ? $profile->harga_promo : $profile->harga_modal;
        $basePrice = $hargaAsli;
        $prorataApplied = false;

        // Prorata otomatis: hanya berlaku untuk invoice pertama saat pendaftaran baru
        if ($applyProrata && $user->prorata_otomatis && $user->jatuh_tempo) {
            $dueDateForProrata = Carbon::parse($user->jatuh_tempo)->startOfDay();
            $today = now()->startOfDay();
            $sisaHari = $today->diffInDays($dueDateForProrata, false); // negatif jika sudah lewat

            // Hitung total hari satu periode (mundur masa_aktif bulan dari jatuh_tempo)
            $masaAktif = max(1, (int) $profile->masa_aktif);
            $periodeStart = $dueDateForProrata->copy()->subMonthsNoOverflow($masaAktif)->addDay();
            $totalHari = $periodeStart->diffInDays($dueDateForProrata) + 1;

            // Prorata hanya berlaku jika sisa hari >= 3 dan lebih kecil dari total periode
            if ($sisaHari >= 3 && $totalHari > 0 && $sisaHari < $totalHari) {
                $basePrice = round($hargaAsli * ($sisaHari / $totalHari), 2);
                $prorataApplied = true;
            }
        }

        // Tagihkan PPN hanya jika flag aktif di user
        $ppnPercent = $user->tagihkan_ppn ? (float) $profile->ppn : 0.0;
        $ppnAmount = round($basePrice * ($ppnPercent / 100), 2);
        $total = $basePrice + $ppnAmount;

        $prefix = TenantSettings::getOrCreate($user->owner_id)->invoice_prefix ?? 'INV';
        $invoiceNumber = Invoice::generateNumber($user->owner_id, $prefix);

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'ppp_user_id' => $user->id,
            'ppp_profile_id' => $user->ppp_profile_id,
            'owner_id' => $user->owner_id,
            'customer_id' => $user->customer_id,
            'customer_name' => $user->customer_name,
            'tipe_service' => $user->tipe_service,
            'paket_langganan' => $profile->name,
            'harga_dasar' => $basePrice,
            'harga_asli' => $hargaAsli,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'total' => $total,
            'promo_applied' => $promoActive,
            'prorata_applied' => $prorataApplied,
            'due_date' => $dueDate,
            'status' => 'unpaid',
            'payment_token' => Invoice::generatePaymentToken(),
        ]);
    }

    private function markInvoicePaid(PppUser $user): void
    {
        $invoice = $user->invoices()->where('status', 'unpaid')->latest()->first();
        if ($invoice) {
            $invoice->update(['status' => 'paid']);
            $user->update(['status_bayar' => 'sudah_bayar']);
        }
    }

    private function ensureInvoiceWindow(PppUser $user): void
    {
        $due = $user->jatuh_tempo ? Carbon::parse($user->jatuh_tempo)->endOfDay() : null;
        if (! $due) {
            return;
        }

        $now = now();
        $windowStart = $due->copy()->subDays(14)->startOfDay();

        if (! $this->userHasInvoiceForDueDate($user, $due) && $now->betweenIncluded($windowStart, $due)) {
            $this->createInvoiceForUser($user, $due);
        }
    }

    private function userHasInvoiceForDueDate(PppUser $user, Carbon $dueDate, ?string $status = null): bool
    {
        $query = $user->invoices()->whereDate('due_date', $dueDate->toDateString());

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->exists();
    }

    private function buildNotaLayananView(PppUser $pppUser, string $requestedType, ?ServiceNote $editingServiceNote = null): View
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorizeServiceNoteWorkspace($user, $pppUser, $editingServiceNote);

        $pppUser->load(['profile', 'owner']);

        $settings = TenantSettings::query()
            ->where('user_id', $pppUser->owner_id)
            ->first();
        $paymentBankAccounts = BankAccount::query()
            ->where('user_id', $pppUser->owner_id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get(['bank_name', 'account_number', 'account_name', 'branch']);

        $notaTypes = $this->notaTypePresets($pppUser);
        $notaType = array_key_exists($requestedType, $notaTypes) ? $requestedType : 'aktivasi';
        $notaDefaults = $notaTypes[$notaType];
        $defaultDocumentNumber = $editingServiceNote?->document_number
            ?? ServiceNote::generateNumber((int) $pppUser->owner_id);

        return view('ppp-users.nota-layanan', compact(
            'editingServiceNote',
            'defaultDocumentNumber',
            'notaDefaults',
            'notaType',
            'notaTypes',
            'paymentBankAccounts',
            'pppUser',
            'settings',
        ));
    }

    private function authorizeServiceNoteWorkspace(User $user, PppUser $pppUser, ?ServiceNote $serviceNote = null): void
    {
        if (! $user->isSuperAdmin() && $pppUser->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if (! $user->isTeknisi()) {
            return;
        }

        if ($serviceNote !== null) {
            $canAccessExisting = $serviceNote->created_by === $user->id || $serviceNote->paid_by === $user->id;

            if (! $canAccessExisting) {
                abort(403);
            }

            return;
        }

        if ($pppUser->assigned_teknisi_id !== null && $pppUser->assigned_teknisi_id !== $user->id) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildServiceNoteAttributes(
        User $user,
        PppUser $pppUser,
        array $validated,
        ?ServiceNote $existingServiceNote = null,
    ): array {
        $itemLines = collect($validated['item_lines'] ?? [])
            ->map(function (array $item): array {
                return [
                    'label' => trim((string) ($item['label'] ?? '')),
                    'amount' => round((float) ($item['amount'] ?? 0), 2),
                ];
            })
            ->filter(fn (array $item): bool => $item['label'] !== '' || $item['amount'] > 0)
            ->values();

        if ($itemLines->isEmpty()) {
            throw ValidationException::withMessages([
                'item_lines' => 'Minimal 1 item biaya harus diisi.',
            ]);
        }

        $subtotal = (float) $itemLines->sum('amount');

        if ($subtotal <= 0) {
            throw ValidationException::withMessages([
                'item_lines' => 'Total nota harus lebih dari nol.',
            ]);
        }

        $documentNumber = trim((string) ($validated['document_number'] ?? ''));

        $documentNumberExists = ServiceNote::query()
            ->where('owner_id', $pppUser->owner_id)
            ->where('document_number', $documentNumber)
            ->when($existingServiceNote !== null, fn (Builder $query) => $query->whereKeyNot($existingServiceNote->id))
            ->exists();

        if ($documentNumber === '' || $documentNumberExists) {
            $documentNumber = ServiceNote::generateNumber((int) $pppUser->owner_id);
        }

        $serviceType = in_array($validated['service_type'], ['pppoe', 'hotspot'], true)
            ? $validated['service_type']
            : 'general';
        $paymentMethod = $validated['payment_method'];
        $transferAccounts = [];

        if ($paymentMethod === 'transfer') {
            $transferAccounts = BankAccount::query()
                ->where('user_id', $pppUser->owner_id)
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->orderByDesc('id')
                ->get(['bank_name', 'account_number', 'account_name', 'branch'])
                ->map(fn (BankAccount $bankAccount): array => [
                    'bank_name' => $bankAccount->bank_name,
                    'account_number' => $bankAccount->account_number,
                    'account_name' => $bankAccount->account_name,
                    'branch' => $bankAccount->branch,
                ])
                ->values()
                ->all();

            if ($transferAccounts === [] && $existingServiceNote?->payment_method === 'transfer') {
                $transferAccounts = $existingServiceNote->transfer_accounts ?? [];
            }

            if ($transferAccounts === []) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Rekening pembayaran aktif belum tersedia. Tambahkan minimal satu rekening pembayaran terlebih dahulu sebelum membuat nota transfer.',
                ]);
            }
        }

        $isTransferPayment = $paymentMethod === 'transfer';
        $paidAt = $isTransferPayment
            ? null
            : Carbon::parse((string) $validated['note_date'])->setTimeFrom(now());

        return [
            'owner_id' => $pppUser->owner_id,
            'ppp_user_id' => $pppUser->id,
            'created_by' => $existingServiceNote?->created_by ?? $user->id,
            'paid_by' => $isTransferPayment ? null : $user->id,
            'note_type' => $validated['note_type'],
            'document_number' => $documentNumber,
            'document_title' => trim((string) $validated['document_title']),
            'summary_title' => trim((string) $validated['summary_title']),
            'service_type' => $serviceType,
            'status' => $isTransferPayment ? ServiceNote::STATUS_PENDING : ServiceNote::STATUS_PAID,
            'note_date' => $validated['note_date'],
            'customer_id' => $pppUser->customer_id,
            'customer_name' => $pppUser->customer_name,
            'customer_phone' => $pppUser->nomor_hp,
            'customer_address' => $pppUser->alamat,
            'package_name' => $pppUser->profile?->name,
            'item_lines' => $itemLines->all(),
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'payment_method' => $paymentMethod,
            'transfer_accounts' => $transferAccounts !== [] ? $transferAccounts : null,
            'show_service_section' => (bool) ($validated['show_service_section'] ?? true),
            'cash_received' => $paymentMethod === 'cash' ? $subtotal : null,
            'notes' => $validated['notes'] ?? null,
            'footer' => $validated['footer'] ?? null,
            'paid_at' => $paidAt,
            'printed_at' => now(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function notaTypePresets(PppUser $pppUser): array
    {
        $installationFee = (float) ($pppUser->biaya_instalasi ?? 0);
        $defaultNotes = trim((string) ($pppUser->catatan ?? ''));

        return [
            'aktivasi' => [
                'label' => 'Nota Aktivasi',
                'document_title' => 'NOTA AKTIVASI PEMASANGAN BARU',
                'summary_title' => 'BIAYA AKTIVASI',
                'description' => 'Preset untuk biaya aktivasi pelanggan baru.',
                'show_service_section' => true,
                'item_lines' => [
                    ['label' => 'Biaya Aktivasi', 'amount' => $installationFee],
                ],
                'notes' => $defaultNotes,
            ],
            'pemasangan' => [
                'label' => 'Nota Biaya Pemasangan',
                'document_title' => 'NOTA BIAYA PEMASANGAN',
                'summary_title' => 'RINCIAN PEMASANGAN',
                'description' => 'Preset untuk biaya pemasangan layanan di lokasi pelanggan.',
                'show_service_section' => true,
                'item_lines' => [
                    ['label' => 'Biaya Pemasangan', 'amount' => $installationFee],
                    ['label' => 'Material / Perlengkapan', 'amount' => 0],
                ],
                'notes' => $defaultNotes,
            ],
            'perbaikan' => [
                'label' => 'Nota Biaya Perbaikan',
                'document_title' => 'NOTA BIAYA PERBAIKAN',
                'summary_title' => 'RINCIAN PERBAIKAN',
                'description' => 'Preset untuk biaya perbaikan, servis, atau penggantian perangkat.',
                'show_service_section' => true,
                'item_lines' => [
                    ['label' => 'Biaya Perbaikan', 'amount' => 0],
                    ['label' => 'Penggantian Material', 'amount' => 0],
                ],
                'notes' => $defaultNotes,
            ],
            'lainnya' => [
                'label' => 'Nota Lainnya',
                'document_title' => 'NOTA BIAYA LAINNYA',
                'summary_title' => 'RINCIAN BIAYA',
                'description' => 'Preset fleksibel untuk kebutuhan nota selain aktivasi, pemasangan, atau perbaikan.',
                'show_service_section' => true,
                'item_lines' => [
                    ['label' => 'Biaya Layanan', 'amount' => 0],
                    ['label' => 'Biaya Tambahan', 'amount' => 0],
                ],
                'notes' => $defaultNotes,
            ],
        ];
    }

    private function enforceOverdueAction(PppUser $user): void
    {
        if (! $user->jatuh_tempo) {
            return;
        }

        $due = Carbon::parse($user->jatuh_tempo)->endOfDay();
        if (now()->greaterThan($due) && $user->aksi_jatuh_tempo === 'isolir' && $user->status_akun !== 'isolir') {
            $settings = TenantSettings::getOrCreate((int) $user->owner_id);
            if (! $settings->auto_isolate_unpaid) {
                return;
            }
            $user->update(['status_akun' => 'isolir']);
            // Sync RADIUS + setup Mikrotik + kick sesi aktif
            app(RadiusReplySynchronizer::class)->syncSingleUser($user);
            app(IsolirSynchronizer::class)->isolate($user);
        }
    }
}
