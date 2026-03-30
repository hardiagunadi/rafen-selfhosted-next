<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateOdpCodeRequest;
use App\Http\Requests\StoreOdpRequest;
use App\Http\Requests\UpdateOdpRequest;
use App\Models\Odp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OdpController extends Controller
{
    public function index(): View
    {
        return view('odps.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = $request->input('search.value', '');

        $query = Odp::query()
            ->with(['owner:id,name,email'])
            ->withCount('pppUsers')
            ->accessibleBy($user)
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('area', 'like', "%{$search}%");
                });
            })
            ->latest();

        $total = Odp::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        $canEdit = true;
        $canDeleteByRole = true;

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(function (Odp $odp) use ($canEdit, $canDeleteByRole): array {
                $usedPorts = (int) $odp->ppp_users_count;
                $capacity = max(0, (int) $odp->capacity_ports);
                $remaining = max(0, $capacity - $usedPorts);

                return [
                    'id' => $odp->id,
                    'code' => $odp->code,
                    'name' => $odp->name,
                    'area' => $odp->area ?: '-',
                    'coordinates' => $odp->latitude !== null && $odp->longitude !== null
                        ? ((string) $odp->latitude).', '.((string) $odp->longitude)
                        : '-',
                    'used_ports' => $usedPorts,
                    'capacity_ports' => $capacity,
                    'remaining_ports' => $remaining,
                    'status' => strtoupper($odp->status),
                    'owner' => $odp->owner?->name ?? $odp->owner?->email ?? '-',
                    'edit_url' => route('odps.edit', $odp),
                    'destroy_url' => route('odps.destroy', $odp),
                    'can_edit' => $canEdit,
                    'can_delete' => $canDeleteByRole && $usedPorts === 0,
                ];
            }),
        ]);
    }

    public function generateCode(GenerateOdpCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $ownerId = (int) $validated['owner_id'];

        if (! $user->isSuperAdmin() && $ownerId !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $locationCode = $this->normalizeCodeSegment((string) $validated['location_code'], 12, 'LOC');
        $areaSegment = $this->normalizeCodeSegment((string) $validated['area_name'], 40, 'WILAYAH');
        $prefix = $locationCode.'-'.$areaSegment;
        $sequence = $this->nextOdpSequence($ownerId, $prefix);
        $code = sprintf('%s-%03d', $prefix, $sequence);

        return response()->json([
            'code' => $code,
            'prefix' => $prefix,
            'sequence' => $sequence,
            'location_code' => $locationCode,
            'area_segment' => $areaSegment,
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();

        $owners = $user->isSuperAdmin()
            ? User::query()->orderBy('name')->get()
            : User::query()->whereKey($user->effectiveOwnerId())->get();

        return view('odps.create', [
            'owners' => $owners,
        ]);
    }

    public function store(StoreOdpRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validated();

        if (! $user->isSuperAdmin()) {
            $data['owner_id'] = $user->effectiveOwnerId();
        }

        Odp::query()->create($data);

        return redirect()->route('odps.index')->with('status', 'Data ODP ditambahkan.');
    }

    public function show(Odp $odp): RedirectResponse
    {
        return redirect()->route('odps.edit', $odp);
    }

    public function edit(Odp $odp): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odp->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $owners = $user->isSuperAdmin()
            ? User::query()->orderBy('name')->get()
            : User::query()->whereKey($user->effectiveOwnerId())->get();

        return view('odps.edit', [
            'odp' => $odp,
            'owners' => $owners,
        ]);
    }

    public function update(UpdateOdpRequest $request, Odp $odp): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $odp->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validated();

        if (! $user->isSuperAdmin()) {
            $data['owner_id'] = $user->effectiveOwnerId();
        }

        $odp->update($data);

        return redirect()->route('odps.index')->with('status', 'Data ODP diperbarui.');
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = $request->input('search', '');

        $odps = Odp::query()
            ->accessibleBy($user)
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('area', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'area', 'code']);

        return response()->json(['data' => $odps]);
    }

    public function destroy(Request $request, Odp $odp): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $odp->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($odp->pppUsers()->exists()) {
            $message = 'ODP tidak bisa dihapus karena sudah terhubung ke data pelanggan.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('odps.index')->with('error', $message);
        }

        $odp->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Data ODP dihapus.']);
        }

        return redirect()->route('odps.index')->with('status', 'Data ODP dihapus.');
    }

    private function normalizeCodeSegment(string $value, int $maxLength, string $fallback): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        if ($normalized === '') {
            return $fallback;
        }

        return (string) Str::of($normalized)->substr(0, $maxLength);
    }

    private function nextOdpSequence(int $ownerId, string $prefix): int
    {
        $maxSequence = 0;
        $prefixPattern = '/^'.preg_quote($prefix, '/').'\-(\d+)$/';

        Odp::query()
            ->where('owner_id', $ownerId)
            ->where('code', 'like', $prefix.'-%')
            ->pluck('code')
            ->each(function (string $code) use (&$maxSequence, $prefixPattern): void {
                if (preg_match($prefixPattern, $code, $matches) === 1) {
                    $maxSequence = max($maxSequence, (int) $matches[1]);
                }
            });

        return $maxSequence + 1;
    }
}
