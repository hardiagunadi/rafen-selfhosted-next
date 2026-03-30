<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotspotProfileRequest;
use App\Http\Requests\UpdateHotspotProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\HotspotProfile;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotspotProfileController extends Controller
{
    public function datatable(Request $request): JsonResponse
    {
        $user   = $request->user();
        $search = $request->input('search.value', '');

        $query = HotspotProfile::query()
            ->accessibleBy($user)
            ->with(['owner', 'profileGroup', 'bandwidthProfile'])
            ->withCount(['hotspotUsers', 'vouchers'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest();

        $total    = HotspotProfile::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 25)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(function ($p) {
                if ($p->profile_type === 'unlimited') {
                    $tipe = '<span class="badge badge-success">Unlimited</span><div class="small text-muted">'.$p->masa_aktif_value.' '.$p->masa_aktif_unit.'</div>';
                } elseif ($p->limit_type === 'time') {
                    $tipe = '<span class="badge badge-info">Limited - Time</span><div class="small text-muted">'.$p->time_limit_value.' '.$p->time_limit_unit.'</div>';
                } elseif ($p->limit_type === 'quota') {
                    $tipe = '<span class="badge badge-info">Limited - Quota</span><div class="small text-muted">'.$p->quota_limit_value.' '.strtoupper($p->quota_limit_unit ?? '').'</div>';
                } else {
                    $tipe = '-';
                }

                $prioritas = $p->prioritas === 'default' ? 'Default' : 'Prioritas '.((int) str_replace('prioritas', '', $p->prioritas));

                $edit = route('hotspot-profiles.edit', $p);
                $del  = route('hotspot-profiles.destroy', $p);
                $aksi = '<a href="'.$edit.'" class="btn btn-sm btn-outline-primary">Edit</a> '
                    .'<button type="button" class="btn btn-sm btn-outline-danger" data-ajax-delete="'.$del.'" data-confirm="Hapus profil ini?">Delete</button>';

                return [
                    'id'                 => $p->id,
                    'name'               => $p->name,
                    'owner_name'         => $p->owner?->name ?? '-',
                    'harga_jual'         => $p->harga_jual,
                    'harga_promo'        => $p->harga_promo,
                    'ppn'                => $p->ppn,
                    'bandwidth_name'     => $p->bandwidthProfile?->name ?? '-',
                    'tipe_profil'        => $tipe,
                    'profile_group_name' => $p->profileGroup?->name ?? '-',
                    'shared_users'        => $p->shared_users,
                    'prioritas_label'     => $prioritas,
                    'hotspot_users_count' => $p->hotspot_users_count,
                    'vouchers_count'      => $p->vouchers_count,
                    'aksi'                => $aksi,
                ];
            }),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('hotspot_profiles.index');
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
        return view('hotspot_profiles.create', [
            'owners'     => $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]),
            'groups'     => ProfileGroup::query()->accessibleBy($user)->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->accessibleBy($user)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHotspotProfileRequest $request): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }
        HotspotProfile::create($this->sanitizeData($request->validated()));

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(HotspotProfile $hotspotProfile): RedirectResponse
    {
        return redirect()->route('hotspot-profiles.edit', $hotspotProfile);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HotspotProfile $hotspotProfile): View
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $hotspotProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        return view('hotspot_profiles.edit', [
            'hotspotProfile' => $hotspotProfile,
            'owners'         => $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]),
            'groups'         => ProfileGroup::query()->accessibleBy($user)->orderBy('name')->get(),
            'bandwidths'     => BandwidthProfile::query()->accessibleBy($user)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHotspotProfileRequest $request, HotspotProfile $hotspotProfile): RedirectResponse
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $hotspotProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        $hotspotProfile->update($this->sanitizeData($request->validated()));

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HotspotProfile $hotspotProfile): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $hotspotProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        $hotspotProfile->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Profil Hotspot dihapus.']);
        }

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user->role === 'teknisi') {
            abort(403);
        }
        $ids  = $request->input('ids', []);
        if (! empty($ids)) {
            HotspotProfile::query()->accessibleBy($user)->whereIn('id', $ids)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Profil Hotspot terpilih dihapus.']);
        }

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $profileType = $data['profile_type'] ?? null;
        $limitType = $data['limit_type'] ?? null;

        if ($profileType === 'unlimited') {
            $data['limit_type'] = null;
            $data['time_limit_value'] = null;
            $data['time_limit_unit'] = null;
            $data['quota_limit_value'] = null;
            $data['quota_limit_unit'] = null;
        }

        if ($profileType === 'limited') {
            $data['masa_aktif_value'] = null;
            $data['masa_aktif_unit'] = null;

            if ($limitType === 'time') {
                $data['quota_limit_value'] = null;
                $data['quota_limit_unit'] = null;
            }

            if ($limitType === 'quota') {
                $data['time_limit_value'] = null;
                $data['time_limit_unit'] = null;
            }
        }

        return $data;
    }
}
