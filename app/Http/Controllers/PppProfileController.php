<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppProfileRequest;
use App\Http\Requests\UpdatePppProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\PppProfile;
use App\Models\ProfileGroup;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PppProfileController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('ppp_profiles.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user   = $request->user();
        $search = $request->input('search.value', '');

        $query = PppProfile::query()
            ->accessibleBy($user)
            ->with(['owner', 'profileGroup', 'bandwidthProfile'])
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest();

        $total    = PppProfile::query()->accessibleBy($user)->count();
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
                'name'        => $r->name,
                'owner_name'  => $r->owner?->name ?? '-',
                'harga_modal' => number_format($r->harga_modal, 0, ',', '.'),
                'harga_promo' => number_format($r->harga_promo, 0, ',', '.'),
                'ppn'         => number_format($r->ppn, 2).'%',
                'group_name'  => $r->profileGroup?->name ?? '-',
                'bandwidth'   => $r->bandwidthProfile?->name ?? '-',
                'masa_aktif'  => $r->masa_aktif.' '.$r->satuan,
                'edit_url'    => route('ppp-profiles.edit', $r->id),
                'destroy_url' => route('ppp-profiles.destroy', $r->id),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        return view('ppp_profiles.create', [
            'owners'     => $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]),
            'groups'     => ProfileGroup::query()->accessibleBy($user)->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->accessibleBy($user)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePppProfileRequest $request): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }
        $profile = PppProfile::create($request->validated());

        $this->logActivity('created', 'PppProfile', $profile->id, $profile->name, (int) $profile->owner_id);

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PppProfile $pppProfile): RedirectResponse
    {
        return redirect()->route('ppp-profiles.edit', $pppProfile);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PppProfile $pppProfile): View
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $pppProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        return view('ppp_profiles.edit', [
            'pppProfile' => $pppProfile,
            'owners'     => $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]),
            'groups'     => ProfileGroup::query()->accessibleBy($user)->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->accessibleBy($user)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePppProfileRequest $request, PppProfile $pppProfile): RedirectResponse
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $pppProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        $pppProfile->update($request->validated());

        $this->logActivity('updated', 'PppProfile', $pppProfile->id, $pppProfile->name, (int) $pppProfile->owner_id);

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppProfile $pppProfile): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $pppProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        $this->logActivity('deleted', 'PppProfile', $pppProfile->id, $pppProfile->name, (int) $pppProfile->owner_id);
        $pppProfile->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Profil PPP dihapus.']);
        }

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        $ids  = $request->input('ids', []);
        if (! empty($ids)) {
            PppProfile::query()
                ->accessibleBy($user)
                ->whereIn('id', $ids)
                ->each(function (PppProfile $p): void {
                    $this->logActivity('deleted', 'PppProfile', $p->id, $p->name, (int) $p->owner_id);
                });
            PppProfile::query()->accessibleBy($user)->whereIn('id', $ids)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Profil PPP terpilih dihapus.']);
        }

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP terpilih dihapus.');
    }
}
