<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoucherBatchRequest;
use App\Models\HotspotProfile;
use App\Models\Voucher;
use App\Services\VoucherGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function __construct(private readonly VoucherGeneratorService $generator) {}

    public function datatable(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);
        $search = $request->input('search.value', '');
        $status = $request->input('status', '');
        $batch  = $request->input('batch', '');

        $query = Voucher::query()->with(['hotspotProfile'])->accessibleBy($currentUser);

        if ($search !== '') {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($status !== '' && in_array($status, ['unused', 'used', 'expired'])) {
            $query->where('status', $status);
        }

        if ($batch !== '' && $batch !== 'null' && $batch !== 'undefined') {
            $query->where('batch_name', $batch);
        }

        $filtered = (clone $query)->count();
        $total    = $filtered;

        $vouchers = $query->latest()->skip($start)->take($length > 0 ? $length : 20)->get();

        $data = $vouchers->map(function (Voucher $voucher) {
            $statusColor = match ($voucher->status) {
                'unused'  => 'success',
                'used'    => 'info',
                'expired' => 'secondary',
                default   => 'light',
            };
            if ($voucher->status === 'unused') {
                $statusLabel = 'Belum Login';
            } elseif ($voucher->status === 'used') {
                $loginDate = $voucher->used_at?->format('d/m/Y H:i') ?? '-';
                $statusLabel = 'Aktif ('.$loginDate.')';
            } else {
                $statusLabel = strtoupper((string) $voucher->status);
            }
            $statusBadge = '<span class="badge badge-'.$statusColor.'">'.$statusLabel.'</span>';

            $isUnused = $voucher->status === 'unused';
            $checkbox = '<input type="checkbox" name="ids[]" value="'.$voucher->id.'"'.($isUnused ? '' : ' disabled').'>';

            $aksi = $isUnused
                ? '<button class="btn btn-sm btn-danger" data-ajax-delete="'.route('vouchers.destroy', $voucher).'" data-confirm="Hapus voucher '.$voucher->code.'?"><i class="fas fa-trash"></i></button>'
                : '<button class="btn btn-sm btn-light" disabled><i class="fas fa-trash"></i></button>';

            return [
                'checkbox'  => $checkbox,
                'code'      => '<code class="font-weight-bold">'.$voucher->code.'</code>',
                'batch'     => $voucher->batch_name ?? '-',
                'profil'    => $voucher->hotspotProfile?->name ?? '-',
                'status'    => $statusBadge,
                'expired'   => $voucher->expired_at?->format('Y-m-d') ?? '-',
                'aksi'      => '<div class="text-right">'.$aksi.'</div>',
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function index(Request $request): View
    {
        $currentUser = $request->user();

        $stats = [
            'unused'  => Voucher::query()->accessibleBy($currentUser)->where('status', 'unused')->count(),
            'used'    => Voucher::query()->accessibleBy($currentUser)->where('status', 'used')->count(),
            'expired' => Voucher::query()->accessibleBy($currentUser)->where('status', 'expired')->count(),
        ];

        $batches = Voucher::query()->accessibleBy($currentUser)->whereNotNull('batch_name')->distinct()->pluck('batch_name');

        return view('vouchers.index', compact('stats', 'batches'));
    }

    public function create(Request $request): View
    {
        $currentUser = $request->user();
        $profiles = HotspotProfile::query()->accessibleBy($currentUser)->orderBy('name')->get();

        return view('vouchers.create', compact('profiles'));
    }

    public function store(StoreVoucherBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        $profile = HotspotProfile::query()->accessibleBy($currentUser)->findOrFail($validated['hotspot_profile_id']);

        $this->generator->generateBatch(
            profile: $profile,
            count: (int) $validated['jumlah'],
            batchName: $validated['batch_name'],
            owner: $currentUser
        );

        return redirect()->route('vouchers.index')->with('status', "Batch voucher '{$validated['batch_name']}' berhasil dibuat.");
    }

    public function printBatch(Request $request, string $batch): View
    {
        $currentUser = $request->user();
        $vouchers = Voucher::query()
            ->accessibleBy($currentUser)
            ->where('batch_name', $batch)
            ->with('hotspotProfile')
            ->get();

        return view('vouchers.print', compact('vouchers', 'batch'));
    }

    public function destroy(Voucher $voucher): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $voucher->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($voucher->status !== 'unused') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Hanya voucher unused yang dapat dihapus.'], 422);
            }

            return redirect()->route('vouchers.index')->with('error', 'Hanya voucher unused yang dapat dihapus.');
        }

        $voucher->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Voucher dihapus.']);
        }

        return redirect()->route('vouchers.index')->with('status', 'Voucher dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            Voucher::query()->whereIn('id', $ids)->accessibleBy($user)->where('status', 'unused')->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Voucher terpilih dihapus.']);
        }

        return redirect()->route('vouchers.index')->with('status', 'Voucher terpilih dihapus.');
    }
}
