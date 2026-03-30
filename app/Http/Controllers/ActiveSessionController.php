<?php

namespace App\Http\Controllers;

use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use App\Services\ActiveSessionFetcher;
use App\Services\MikrotikApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ActiveSessionController extends Controller
{
    public function pppoe(Request $request): View
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        $total = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->accessibleBy($user)
            ->count();

        return view('sessions.pppoe', compact('routers', 'total'));
    }

    public function hotspot(Request $request): View
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        $total = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', true)
            ->accessibleBy($user)
            ->count();

        return view('sessions.hotspot', compact('routers', 'total'));
    }

    public function pppoeDatatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->filled('router_id'), fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('radius_accounts.username', 'like', "%{$search}%")
                    ->orWhere('radius_accounts.ipv4_address', 'like', "%{$search}%")
                    ->orWhere('radius_accounts.caller_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('radius_accounts.updated_at');

        $total = RadiusAccount::where('service', 'pppoe')->where('is_active', true)->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'username' => $r->username,
                'ipv4' => $r->ipv4_address ?? '-',
                'uptime' => $r->uptime ?? '-',
                'caller_id' => $r->caller_id ?? '-',
                'bytes_in' => $this->formatBytes($r->bytes_in),
                'bytes_out' => $this->formatBytes($r->bytes_out),
                'profile' => $r->profile ?? '-',
                'router' => $r->mikrotikConnection?->name ?? '-',
                'updated_at' => $r->updated_at?->diffForHumans() ?? '-',
            ]),
        ]);
    }

    public function hotspotDatatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->filled('router_id'), fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('username', 'like', "%{$search}%")
                    ->orWhere('ipv4_address', 'like', "%{$search}%")
                    ->orWhere('caller_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('updated_at');

        $total = RadiusAccount::where('service', 'hotspot')->where('is_active', true)->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'username' => $r->username,
                'ipv4' => $r->ipv4_address ?? '-',
                'caller_id' => $r->caller_id ?? '-',
                'uptime' => $r->uptime ?? '-',
                'bytes_in' => $this->formatBytes($r->bytes_in),
                'bytes_out' => $this->formatBytes($r->bytes_out),
                'server_name' => $r->server_name ?? '-',
                'router' => $r->mikrotikConnection?->name ?? '-',
                'updated_at' => $r->updated_at?->diffForHumans() ?? '-',
            ]),
        ]);
    }

    public function pppoeInactive(Request $request): View
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        $total = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', false)
            ->accessibleBy($user)
            ->count();

        return view('sessions.pppoe_inactive', compact('routers', 'total'));
    }

    public function hotspotInactive(Request $request): View
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        $total = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', false)
            ->accessibleBy($user)
            ->count();

        return view('sessions.hotspot_inactive', compact('routers', 'total'));
    }

    public function pppoeInactiveDatatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', false)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->filled('router_id'), fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('radius_accounts.username', 'like', "%{$search}%")
                    ->orWhere('radius_accounts.ipv4_address', 'like', "%{$search}%")
                    ->orWhere('radius_accounts.caller_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('radius_accounts.updated_at');

        $total = RadiusAccount::where('service', 'pppoe')->where('is_active', false)->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'username' => $r->username,
                'ipv4' => $r->ipv4_address ?? '-',
                'caller_id' => $r->caller_id ?? '-',
                'profile' => $r->profile ?? '-',
                'router' => $r->mikrotikConnection?->name ?? '-',
                'updated_at' => $r->updated_at?->diffForHumans() ?? '-',
            ]),
        ]);
    }

    public function hotspotInactiveDatatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', false)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->filled('router_id'), fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('username', 'like', "%{$search}%")
                    ->orWhere('ipv4_address', 'like', "%{$search}%")
                    ->orWhere('caller_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('updated_at');

        $total = RadiusAccount::where('service', 'hotspot')->where('is_active', false)->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'username' => $r->username,
                'ipv4' => $r->ipv4_address ?? '-',
                'caller_id' => $r->caller_id ?? '-',
                'server_name' => $r->server_name ?? '-',
                'router' => $r->mikrotikConnection?->name ?? '-',
                'updated_at' => $r->updated_at?->diffForHumans() ?? '-',
            ]),
        ]);
    }

    public function refreshRouter(Request $request, MikrotikConnection $connection): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $connection->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $service = $request->input('service', 'pppoe');

        try {
            $fetcher = new ActiveSessionFetcher(new MikrotikApiClient($connection));
            $count = $service === 'hotspot'
                ? $fetcher->syncHotspot($connection)
                : $fetcher->syncPpp($connection);

            return response()->json([
                'success' => true,
                'synced' => $count,
                'router' => $connection->name,
                'message' => "{$count} sesi aktif ditemukan di {$connection->name}",
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatBytes(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return '-';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1073741824) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1073741824, 2).' GB';
    }

    public function refreshAll(Request $request): JsonResponse
    {
        $user = auth()->user();
        $service = strtolower((string) $request->input('service', 'all'));
        if (! in_array($service, ['all', 'pppoe', 'ppp', 'hotspot'], true)) {
            $service = 'all';
        }
        $syncPpp = in_array($service, ['all', 'pppoe', 'ppp'], true);
        $syncHotspot = in_array($service, ['all', 'hotspot'], true);

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->get();

        $pppTotal = 0;
        $hotspotTotal = 0;
        $errors = [];

        foreach ($routers as $router) {
            $fetcher = new ActiveSessionFetcher(new MikrotikApiClient($router));
            if ($syncPpp) {
                try {
                    $pppTotal += $fetcher->syncPpp($router);
                } catch (RuntimeException $e) {
                    $errors[] = $router->name.': '.$e->getMessage();
                }
            }
            if ($syncHotspot) {
                try {
                    $hotspotTotal += $fetcher->syncHotspot($router);
                } catch (RuntimeException $e) {
                    $errors[] = $router->name.': '.$e->getMessage();
                }
            }
        }

        $message = match (true) {
            $syncPpp && $syncHotspot => "PPPoE: {$pppTotal}, Hotspot: {$hotspotTotal} sesi aktif",
            $syncPpp => "PPPoE: {$pppTotal} sesi aktif",
            $syncHotspot => "Hotspot: {$hotspotTotal} sesi aktif",
            default => 'Tidak ada layanan yang disinkronkan.',
        };

        return response()->json([
            'success' => true,
            'ppp_online' => $pppTotal,
            'hotspot_online' => $hotspotTotal,
            'errors' => $errors,
            'message' => $message,
        ]);
    }
}
