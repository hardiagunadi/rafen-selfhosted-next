<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\TeknisiSetoran;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeknisiSetoranController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        // Hanya admin, keuangan, teknisi yang boleh akses
        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'keuangan', 'teknisi'])) {
            abort(403);
        }

        return view('teknisi-setoran.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'keuangan', 'teknisi'])) {
            abort(403);
        }

        $search = $request->input('search.value', '');

        $query = TeknisiSetoran::query()
            ->with(['teknisi', 'verifiedBy'])
            ->accessibleBy($user)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('teknisi_id'), fn ($q) => $q->where('teknisi_id', $request->teknisi_id))
            ->when($search !== '', fn ($q) => $q->whereHas('teknisi', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->orderByDesc('period_date');

        $total = (clone $query)->count();
        $filtered = $total;
        $totalTagihan = (float) (clone $query)->sum('total_tagihan');
        $totalCash = (float) (clone $query)->sum('total_cash');
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'summary' => [
                'total_tagihan' => $totalTagihan,
                'total_cash' => $totalCash,
                'total_tagihan_formatted' => number_format($totalTagihan, 0, ',', '.'),
                'total_cash_formatted' => number_format($totalCash, 0, ',', '.'),
            ],
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'period_date' => $r->period_date->format('Y-m-d'),
                'teknisi_name' => $r->teknisi?->name ?? '-',
                'total_invoices' => $r->total_invoices,
                'total_tagihan' => number_format($r->total_tagihan, 0, ',', '.'),
                'total_cash' => number_format($r->total_cash, 0, ',', '.'),
                'status' => $r->status,
                'verified_by' => $r->verifiedBy?->name ?? '-',
                'submitted_at' => $r->submitted_at?->format('Y-m-d H:i') ?? '-',
                'verified_at' => $r->verified_at?->format('Y-m-d H:i') ?? '-',
                'show_url' => route('teknisi-setoran.show', $r->id),
            ]),
        ]);
    }

    public function show(TeknisiSetoran $teknisiSetoran): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $teknisiSetoran->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        if ($user->role === 'teknisi' && $teknisiSetoran->teknisi_id !== $user->id) {
            abort(403);
        }

        $teknisiSetoran->load(['teknisi', 'verifiedBy']);
        $invoices = $teknisiSetoran->getInvoices();

        return view('teknisi-setoran.show', compact('teknisiSetoran', 'invoices'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        // Teknisi buat setoran untuk dirinya sendiri; admin/keuangan bisa tentukan teknisi_id
        $teknisiId = $user->role === 'teknisi' ? $user->id : $request->integer('teknisi_id');
        $periodDate = $request->input('period_date', today()->toDateString());
        $ownerId = $user->effectiveOwnerId();

        // Cegah duplikat
        $existing = TeknisiSetoran::where('owner_id', $ownerId)
            ->where('teknisi_id', $teknisiId)
            ->where('period_date', $periodDate)
            ->first();

        if ($existing) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Setoran untuk periode ini sudah ada.'], 422);
            }

            return redirect()->route('teknisi-setoran.show', $existing)->with('status', 'Setoran sudah ada.');
        }

        // Hitung dari invoice
        $invoices = Invoice::where('paid_by', $teknisiId)
            ->whereDate('paid_at', $periodDate)
            ->where('owner_id', $ownerId)
            ->get();

        if ($invoices->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Tidak ada invoice yang dibayar pada periode ini.'], 422);
            }

            return redirect()->back()->with('error', 'Tidak ada invoice yang dibayar pada periode ini.');
        }

        $setoran = TeknisiSetoran::create([
            'owner_id' => $ownerId,
            'teknisi_id' => $teknisiId,
            'period_date' => $periodDate,
            'total_invoices' => $invoices->count(),
            'total_tagihan' => $invoices->sum('total'),
            'total_cash' => $invoices->sum('cash_received'),
            'status' => 'draft',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Setoran dibuat.', 'id' => $setoran->id]);
        }

        return redirect()->route('teknisi-setoran.show', $setoran)->with('status', 'Setoran berhasil dibuat.');
    }

    public function submit(TeknisiSetoran $teknisiSetoran): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $teknisiSetoran->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        if ($user->role === 'teknisi' && $teknisiSetoran->teknisi_id !== $user->id) {
            abort(403);
        }
        if ($teknisiSetoran->status !== 'draft') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Setoran sudah disubmit atau diverifikasi.'], 422);
            }

            return redirect()->back()->with('error', 'Setoran sudah disubmit atau diverifikasi.');
        }

        // Recalculate sebelum submit
        $teknisiSetoran->recalculate();
        $teknisiSetoran->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Setoran disubmit ke keuangan.']);
        }

        return redirect()->back()->with('status', 'Setoran berhasil disubmit ke keuangan.');
    }

    public function verify(Request $request, TeknisiSetoran $teknisiSetoran): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, ['administrator', 'keuangan'])) {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $teknisiSetoran->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        if ($teknisiSetoran->status !== 'submitted') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Setoran belum disubmit atau sudah diverifikasi.'], 422);
            }

            return redirect()->back()->with('error', 'Setoran belum disubmit atau sudah diverifikasi.');
        }

        $teknisiSetoran->update([
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Setoran diverifikasi.']);
        }

        return redirect()->back()->with('status', 'Setoran berhasil diverifikasi.');
    }

    public function teknisiList(Request $request): JsonResponse
    {
        $user = auth()->user();

        $teknisis = User::where('parent_id', $user->effectiveOwnerId())
            ->where('role', 'teknisi')
            ->get(['id', 'name']);

        return response()->json($teknisis);
    }
}
