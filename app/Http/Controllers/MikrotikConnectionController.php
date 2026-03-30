<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMikrotikConnectionRequest;
use App\Http\Requests\TestMikrotikConnectionRequest;
use App\Http\Requests\UpdateMikrotikConnectionRequest;
use App\Models\MikrotikConnection;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\MikrotikPingService;
use App\Services\RadiusClientsSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class MikrotikConnectionController extends Controller
{
    public function __construct(
        private RadiusClientsSynchronizer $synchronizer,
        private MikrotikPingService $pingService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('mikrotik_connections.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = $request->input('search.value', '');
        $staleSeconds = (int) config('ping.stale_seconds', 600);

        $query = MikrotikConnection::query()
            ->accessibleBy($user)
            ->withCount('radiusAccounts')
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('host', 'like', "%{$search}%");
            }))
            ->latest();

        $total = MikrotikConnection::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        $isReadOnly = $user->role === 'teknisi';

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(function ($r) use ($staleSeconds, $isReadOnly) {
                $isStale = $r->last_ping_at && $r->last_ping_at->diffInSeconds(now()) > $staleSeconds;

                if ($r->is_online === null) {
                    $pingStatus = 'Belum Dicek';
                    $pingClass = 'badge-secondary';
                } elseif ($isStale) {
                    $pingStatus = 'Tidak Terhubung';
                    $pingClass = 'badge-danger';
                } elseif ($r->ping_unstable) {
                    $pingStatus = 'Tidak Stabil';
                    $pingClass = 'badge-warning';
                } elseif ($r->is_online) {
                    $pingStatus = 'Terhubung';
                    $pingClass = 'badge-success';
                } else {
                    $pingStatus = 'Tidak Terhubung';
                    $pingClass = 'badge-danger';
                }

                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'host' => $r->host,
                    'ping_status' => $pingStatus,
                    'ping_class' => $pingClass,
                    'ping_message' => $r->last_ping_message ?? '-',
                    'last_ping_at' => $r->last_ping_at?->format('Y-m-d H:i:s') ?? '-',
                    'radius_count' => $r->radius_accounts_count,
                    'api_url' => route('dashboard.api').'?connection_id='.$r->id,
                    'edit_url' => route('mikrotik-connections.edit', $r->id),
                    'destroy_url' => route('mikrotik-connections.destroy', $r->id),
                    'can_edit' => ! $isReadOnly,
                ];
            }),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        return view('mikrotik_connections.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMikrotikConnectionRequest $request): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $ownerId = auth()->user()->effectiveOwnerId();
        $owner = User::query()->find($ownerId);

        if ($owner && $owner->hasReachedMikrotikLimit($ownerId)) {
            $limit = $owner->getEffectiveMikrotikLimit();

            return back()
                ->withInput()
                ->withErrors([
                    'name' => "Batas koneksi Mikrotik tenant sudah tercapai ({$limit}). Ubah limit lisensi/paket terlebih dahulu.",
                ]);
        }

        $data = $request->validated();
        $data['owner_id'] = $ownerId;
        $data['use_ssl'] = $request->boolean('use_ssl');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['username'] = $data['username'] ?: $this->generateApiUsername();
        $data['password'] = $data['password'] ?: $this->generateApiSecret();
        $data['radius_secret'] = $data['radius_secret'] ?: $data['password'];
        $data['monitor_interface'] = $data['monitor_interface'] ?? null;
        $data['timezone'] = $data['timezone'] ?? '+07:00 Asia/Jakarta';
        $data['ros_version'] = $data['ros_version'] ?? 'auto';

        MikrotikConnection::create($data);

        return $this->syncAndRedirect('Koneksi Mikrotik berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MikrotikConnection $mikrotikConnection): RedirectResponse
    {
        return redirect()->route('mikrotik-connections.edit', $mikrotikConnection);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MikrotikConnection $mikrotikConnection): View
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($mikrotikConnection);

        // Resolve the PUBLIC IP/host of the FreeRADIUS server for script generation.
        // WG_HOST is the canonical public IP/domain of this server — always use it first.
        // Never use WG_SERVER_IP or RADIUS_SERVER_IP as they are tunnel/internal IPs.
        $radiusHost = (string) config('wg.host');
        if ($radiusHost === '') {
            // Auto-detect public IP via ipify
            $output = @shell_exec('curl -s --max-time 3 https://api.ipify.org 2>/dev/null');
            if ($output !== null) {
                $candidate = trim($output);
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $radiusHost = $candidate;
                }
            }
        }

        $mikrotikConnection->load('wgPeer');

        // Jika router menggunakan IP WireGuard (ada wgPeer), login address RADIUS
        // harus menggunakan IP tunnel server (WG_SERVER_IP), bukan IP publik.
        if ($mikrotikConnection->wgPeer) {
            $wgServerIp = (string) config('wg.server_ip');
            if ($wgServerIp !== '') {
                $radiusHost = $wgServerIp;
            }
        }

        // Deteksi mismatch: cek apakah secret di clients.conf sudah cocok dengan DB
        $radiusSecretMismatch = false;
        $clientsPath = (string) config('radius.clients_path');
        if ($mikrotikConnection->radius_secret && file_exists($clientsPath) && is_readable($clientsPath)) {
            $contents = file_get_contents($clientsPath);
            $radiusSecretMismatch = $contents !== false
                && ! str_contains($contents, 'secret = '.$mikrotikConnection->radius_secret);
        }

        return view('mikrotik_connections.edit', compact('mikrotikConnection', 'radiusHost', 'radiusSecretMismatch'));
    }

    public function pingNow(MikrotikConnection $mikrotikConnection): JsonResponse
    {
        $this->authorizeAccess($mikrotikConnection);

        try {
            $this->pingService->ping($mikrotikConnection);
            $mikrotikConnection->refresh();

            return response()->json([
                'is_online' => $mikrotikConnection->is_online,
                'ping_unstable' => $mikrotikConnection->ping_unstable,
                'message' => $mikrotikConnection->last_ping_message,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Ping gagal: '.$e->getMessage()], 500);
        }
    }

    public function syncRadiusClients(): JsonResponse
    {
        try {
            $this->synchronizer->sync();

            return response()->json(['status' => 'RADIUS clients.conf berhasil disinkron.']);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Sync gagal: '.$e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMikrotikConnectionRequest $request, MikrotikConnection $mikrotikConnection): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($mikrotikConnection);

        $data = $request->validated();
        $data['use_ssl'] = $request->boolean('use_ssl', $mikrotikConnection->use_ssl);
        $data['is_active'] = $request->boolean('is_active', $mikrotikConnection->is_active);
        $data['radius_secret'] = $data['radius_secret'] ?? $mikrotikConnection->radius_secret;
        $data['monitor_interface'] = $data['monitor_interface'] ?? $mikrotikConnection->monitor_interface;
        $data['timezone'] = $data['timezone'] ?? $mikrotikConnection->timezone ?? '+07:00 Asia/Jakarta';
        $data['ros_version'] = $data['ros_version'] ?? $mikrotikConnection->ros_version ?? 'auto';

        // Jika konfigurasi isolir berubah, reset setup agar diterapkan ulang
        $isolirFields = ['isolir_pool_name', 'isolir_pool_range', 'isolir_gateway', 'isolir_profile_name', 'isolir_rate_limit', 'isolir_url'];
        foreach ($isolirFields as $field) {
            if (isset($data[$field]) && $data[$field] !== $mikrotikConnection->$field) {
                $data['isolir_setup_done'] = false;
                $data['isolir_setup_at'] = null;
                break;
            }
        }

        $mikrotikConnection->update($data);

        return $this->syncAndRedirect('Koneksi Mikrotik berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MikrotikConnection $mikrotikConnection): JsonResponse|RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($mikrotikConnection);

        $mikrotikConnection->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Koneksi Mikrotik dihapus.']);
        }

        return $this->syncAndRedirect('Koneksi Mikrotik dihapus.');
    }

    public function test(TestMikrotikConnectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $timeout = (int) ($data['api_timeout'] ?? 10);
        $useSsl = (bool) ($data['use_ssl'] ?? false);
        $port = $useSsl
            ? (int) ($data['api_ssl_port'] ?? 8729)
            : (int) ($data['api_port'] ?? 8728);

        $result = $this->pingService->probe($data['host'], $timeout, $port, $useSsl);
        $message = $result['online']
            ? 'Koneksi OK'.($result['latency'] ? " ({$result['latency']} ms)" : '')
            : ($result['ping_success']
                ? 'Ping OK, port API '.$data['host'].':'.$port.' tertutup'
                : 'Ping ke '.$data['host'].' gagal');

        return response()->json([
            'success' => $result['online'],
            'latency' => $result['latency'],
            'port_open' => $result['port_open'],
            'message' => $message,
        ], $result['online'] ? 200 : 422);
    }

    private function syncAndRedirect(string $message): RedirectResponse
    {
        try {
            $this->synchronizer->sync();

            return redirect()
                ->route('mikrotik-connections.index')
                ->with('status', $message.' Radius clients.conf disinkron.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('mikrotik-connections.index')
                ->with('status', $message)
                ->with('error', 'Sinkronisasi RADIUS gagal: '.$exception->getMessage());
        }
    }

    private function generateApiUsername(): string
    {
        return 'TMDRadius'.Str::upper(Str::random(6));
    }

    private function generateApiSecret(): string
    {
        return Str::password(10);
    }

    public function isolirReset(MikrotikConnection $mikrotikConnection): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($mikrotikConnection);

        app(IsolirSynchronizer::class)->resetSetup($mikrotikConnection);

        return back()->with('status', 'Status setup isolir direset. Akan disetup ulang saat user berikutnya diisolir.');
    }

    private function authorizeAccess(MikrotikConnection $connection): void
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $connection->owner_id !== $user->effectiveOwnerId()) {
            abort(403, 'Anda tidak memiliki akses ke koneksi ini.');
        }
    }
}
