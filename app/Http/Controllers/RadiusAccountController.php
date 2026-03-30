<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRadiusAccountRequest;
use App\Http\Requests\UpdateRadiusAccountRequest;
use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RadiusAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('radius_accounts.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user   = $request->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->accessibleBy($user)
            ->with('mikrotikConnection')
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('username', 'like', "%{$search}%")
                   ->orWhere('service', 'like', "%{$search}%");
            }))
            ->latest();

        $total    = RadiusAccount::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'          => $r->id,
                'username'    => $r->username,
                'service'     => strtoupper($r->service),
                'ip_address'  => $r->service === 'pppoe' ? ($r->ipv4_address ?? '-') : '-',
                'rate_limit'  => $r->rate_limit ?? '-',
                'router'      => $r->mikrotikConnection?->name ?? '-',
                'is_active'   => (bool) $r->is_active,
                'edit_url'    => route('radius-accounts.edit', $r->id),
                'destroy_url' => route('radius-accounts.destroy', $r->id),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $user = auth()->user();
        $mikrotikConnections = MikrotikConnection::query()
            ->accessibleBy($user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('radius_accounts.create', compact('mikrotikConnections'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRadiusAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        if (($data['service'] ?? null) !== 'pppoe') {
            $data['ipv4_address'] = null;
        }

        RadiusAccount::create($data);

        return redirect()
            ->route('radius-accounts.index')
            ->with('status', 'Akun RADIUS berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RadiusAccount $radiusAccount): RedirectResponse
    {
        return redirect()->route('radius-accounts.edit', $radiusAccount);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RadiusAccount $radiusAccount): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $radiusAccount->mikrotikConnection?->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $mikrotikConnections = MikrotikConnection::query()
            ->accessibleBy($user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('radius_accounts.edit', compact('radiusAccount', 'mikrotikConnections'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRadiusAccountRequest $request, RadiusAccount $radiusAccount): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $radiusAccount->is_active);

        if (($data['service'] ?? $radiusAccount->service) !== 'pppoe') {
            $data['ipv4_address'] = null;
        }

        $radiusAccount->update($data);

        return redirect()
            ->route('radius-accounts.index')
            ->with('status', 'Akun RADIUS diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RadiusAccount $radiusAccount): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $radiusAccount->mikrotikConnection?->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $radiusAccount->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Akun RADIUS dihapus.']);
        }

        return redirect()
            ->route('radius-accounts.index')
            ->with('status', 'Akun RADIUS dihapus.');
    }
}
