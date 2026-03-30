<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkExportProfileGroupRequest;
use App\Http\Requests\StoreProfileGroupRequest;
use App\Http\Requests\UpdateProfileGroupRequest;
use App\Models\MikrotikConnection;
use App\Models\ProfileGroup;
use App\Models\User;
use App\Services\MikrotikApiClient;
use App\Services\ProfileGroupExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class ProfileGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $user = auth()->user();
        $mikrotikConnections = MikrotikConnection::query()->accessibleBy($user)->orderBy('name')->get();

        return view('profile_groups.index', compact('mikrotikConnections'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = $request->input('search.value', '');
        $validTypes = ['pppoe', 'hotspot'];
        $typeFilter = strtolower(trim((string) $request->input('filter_type', '')));
        $normalizedTypeFilter = in_array($typeFilter, $validTypes, true) ? $typeFilter : null;

        $query = ProfileGroup::query()
            ->accessibleBy($user)
            ->with('mikrotikConnection')
            ->when($normalizedTypeFilter !== null, fn ($q) => $q->whereRaw('LOWER(type) = ?', [$normalizedTypeFilter]))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest();

        $total = ProfileGroup::query()->accessibleBy($user)->count();
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
                'name' => $r->name,
                'owner' => $r->owner ?? '-',
                'router' => $r->mikrotikConnection?->name ?? 'Semua Router',
                'type' => strtoupper($r->type),
                'ip_pool_mode' => $r->ip_pool_mode === 'sql' ? 'SQL IP Pool' : 'Group Only',
                'pool_info' => $r->ip_pool_mode === 'group_only'
                    ? ($r->ip_pool_name ?? '-')
                    : ($r->ip_address ? $r->ip_address.'/'.$r->netmask : '-'),
                'edit_url' => route('profile-groups.edit', $r->id),
                'destroy_url' => route('profile-groups.destroy', $r->id),
                'export_url' => route('profile-groups.export', $r->id),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $user = auth()->user();
        $mikrotikConnections = MikrotikConnection::query()->accessibleBy($user)->orderBy('name')->get();
        $users = $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]);

        return view('profile_groups.create', compact('mikrotikConnections', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProfileGroupRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $data = $this->hydrateHostRange($request->validated());
        $data['owner_id'] = $user->effectiveOwnerId();

        ProfileGroup::create($data);

        return redirect()->route('profile-groups.index')->with('status', 'Profil group ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ProfileGroup $profileGroup): RedirectResponse
    {
        return redirect()->route('profile-groups.edit', $profileGroup);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProfileGroup $profileGroup): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $profileGroup->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $mikrotikConnections = MikrotikConnection::query()->accessibleBy($user)->orderBy('name')->get();
        $users = $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]);

        return view('profile_groups.edit', compact('profileGroup', 'mikrotikConnections', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfileGroupRequest $request, ProfileGroup $profileGroup): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $profileGroup->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $this->hydrateHostRange($request->validated());

        $profileGroup->update($data);

        return redirect()->route('profile-groups.index')->with('status', 'Profil group diperbarui.');
    }

    /**
     * Fetch queue tree names from Mikrotik via API (AJAX).
     */
    public function mikrotikQueues(Request $request): JsonResponse
    {
        $user = auth()->user();
        $connections = MikrotikConnection::query()->accessibleBy($user)->get();

        if ($connections->isEmpty()) {
            return response()->json(['error' => 'Tidak ada koneksi Mikrotik.'], 404);
        }

        $allQueues = collect();
        $lastError = null;

        foreach ($connections as $conn) {
            try {
                $client = new MikrotikApiClient($conn);
                $result = $client->command('/queue/simple/print');
                $client->disconnect();

                $allQueues = $allQueues->merge(
                    collect($result['data'] ?? [])
                        ->pluck('name')
                        ->filter(fn ($n) => $n && ! str_starts_with($n, '<'))
                );
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        $queues = $allQueues->unique()->sort()->values()->all();

        if (empty($queues) && $lastError) {
            return response()->json(['error' => 'Gagal konek ke Mikrotik: '.$lastError], 500);
        }

        return response()->json(['queues' => $queues]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProfileGroup $profileGroup): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $profileGroup->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $profileGroup->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Profil group dihapus.']);
        }

        return redirect()->route('profile-groups.index')->with('status', 'Profil group dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            ProfileGroup::query()->whereIn('id', $ids)->accessibleBy($user)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Profil group terpilih dihapus.']);
        }

        return redirect()->route('profile-groups.index')->with('status', 'Profil group terpilih dihapus.');
    }

    public function export(ProfileGroup $profileGroup, ProfileGroupExporter $exporter): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $profileGroup->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $connections = $this->resolveExportConnections($profileGroup);
        if ($connections->isEmpty()) {
            return redirect()
                ->route('profile-groups.index')
                ->with('error', 'Tidak ada koneksi Mikrotik aktif untuk export profil group.');
        }

        $success = [];
        $errors = [];

        foreach ($connections as $connection) {
            try {
                $exporter->export($profileGroup, $connection);
                $success[] = $connection->name;
            } catch (Throwable $exception) {
                $errors[] = $connection->name.': '.$exception->getMessage();
            }
        }

        $redirect = redirect()->route('profile-groups.index');

        if (! empty($success)) {
            $redirect = $redirect->with('status', 'Export profil group sukses: '.implode(', ', $success).'.');
        }

        if (! empty($errors)) {
            $redirect = $redirect->with('error', 'Sebagian export gagal: '.implode(' | ', $errors));
        }

        return $redirect;
    }

    public function bulkExport(BulkExportProfileGroupRequest $request, ProfileGroupExporter $exporter): RedirectResponse
    {
        $user = auth()->user();
        $groupIds = $request->input('profile_group_ids', []);
        $connectionIds = $request->input('mikrotik_connection_ids', []);

        $groups = ProfileGroup::query()
            ->whereIn('id', $groupIds)
            ->accessibleBy($user)
            ->get();

        $connections = MikrotikConnection::query()
            ->whereIn('id', $connectionIds)
            ->accessibleBy($user)
            ->get();

        if ($groups->isEmpty() || $connections->isEmpty()) {
            return redirect()
                ->route('profile-groups.index')
                ->with('error', 'Data export tidak lengkap.');
        }

        $success = [];
        $errors = [];

        foreach ($groups as $group) {
            foreach ($connections as $connection) {
                try {
                    $exporter->export($group, $connection);
                    $success[] = $group->name.' -> '.$connection->name;
                } catch (Throwable $exception) {
                    $errors[] = $group->name.' -> '.$connection->name.': '.$exception->getMessage();
                }
            }
        }

        $redirect = redirect()->route('profile-groups.index');

        if (! empty($success)) {
            $redirect = $redirect->with('status', 'Export profil group sukses: '.implode(', ', $success).'.');
        }

        if (! empty($errors)) {
            $redirect = $redirect->with('error', 'Sebagian export gagal: '.implode(' | ', $errors));
        }

        return $redirect;
    }

    private function hydrateHostRange(array $data): array
    {
        if (($data['type'] ?? null) === 'hotspot') {
            $data['dns_servers'] = null;
        }

        if (($data['ip_pool_mode'] ?? null) !== 'sql') {
            $data['host_min'] = null;
            $data['host_max'] = null;

            return $data;
        }

        if (empty($data['ip_address']) || empty($data['netmask'])) {
            return $data;
        }

        [$hostMin, $hostMax] = $this->calculateHostRange($data['ip_address'], $data['netmask']);
        $data['host_min'] = $hostMin;
        $data['host_max'] = $hostMax;

        return $data;
    }

    private function calculateHostRange(string $ip, string $netmask): array
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return [null, null];
        }

        $maskLong = str_contains($netmask, '.')
            ? ip2long($netmask)
            : (~0 << (32 - (int) $netmask));

        if ($maskLong === false) {
            return [null, null];
        }

        $network = $ipLong & $maskLong;
        $broadcast = $network | (~$maskLong);

        $hostMin = $network + 1;
        $hostMax = $broadcast - 1;

        return [long2ip($hostMin), long2ip($hostMax)];
    }

    /**
     * @return Collection<int, MikrotikConnection>
     */
    private function resolveExportConnections(ProfileGroup $profileGroup): Collection
    {
        $user = auth()->user();

        if ($profileGroup->mikrotik_connection_id) {
            return MikrotikConnection::query()
                ->whereKey($profileGroup->mikrotik_connection_id)
                ->accessibleBy($user)
                ->get();
        }

        return MikrotikConnection::query()
            ->accessibleBy($user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
