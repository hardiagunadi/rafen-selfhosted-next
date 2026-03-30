<?php

namespace App\Http\Controllers;

use App\Models\TenantWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\TenantWalletService;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    use LogsActivity;

    public function walletBalances(Request $request)
    {
        $wallets = TenantWallet::with('owner')
            ->orderByDesc('balance')
            ->get();

        return view('super-admin.wallets.index', compact('wallets'));
    }

    public function index(Request $request)
    {
        return view('super-admin.withdrawal-requests.index');
    }

    public function datatable(Request $request)
    {
        $query = WithdrawalRequest::with('owner')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'settled', 'rejected')")
            ->orderByDesc('created_at');

        $total    = WithdrawalRequest::count();
        $filtered = $query->count();

        $requests = $query
            ->skip($request->input('start', 0))
            ->take($request->input('length', 10))
            ->get();

        $data = $requests->map(fn ($wr) => [
            'id'             => $wr->id,
            'request_number' => $wr->request_number,
            'tenant'         => $wr->owner?->name . ' (' . ($wr->owner?->company_name ?? '-') . ')',
            'amount'         => 'Rp ' . number_format($wr->amount, 0, ',', '.'),
            'status'         => $wr->status,
            'bank_info'      => $wr->bank_name . ' - ' . $wr->bank_account_number . '<br><small>' . $wr->bank_account_name . '</small>',
            'created_at'     => $wr->created_at->format('d/m/Y H:i'),
            'actions'        => $wr->id, // used in JS to build action buttons
        ]);

        return response()->json([
            'draw'            => $request->input('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function approve(WithdrawalRequest $withdrawal)
    {
        if (! $withdrawal->isPending()) {
            return response()->json(['success' => false, 'message' => 'Permintaan tidak dalam status pending.'], 422);
        }

        $withdrawal->update([
            'status'       => 'approved',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $this->logActivity('approved', 'WithdrawalRequest', $withdrawal->id, $withdrawal->request_number . ' — Rp ' . number_format($withdrawal->amount, 0, ',', '.'));

        return response()->json(['success' => true, 'message' => 'Permintaan penarikan disetujui.']);
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if (! $withdrawal->isPending()) {
            return response()->json(['success' => false, 'message' => 'Permintaan tidak dalam status pending.'], 422);
        }

        $withdrawal->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'processed_by'     => auth()->id(),
            'processed_at'     => now(),
        ]);

        $this->logActivity('rejected', 'WithdrawalRequest', $withdrawal->id, $withdrawal->request_number . ' — ' . $request->rejection_reason);

        return response()->json(['success' => true, 'message' => 'Permintaan penarikan ditolak.']);
    }

    public function settle(Request $request, WithdrawalRequest $withdrawal)
    {
        $request->validate([
            'admin_notes'    => 'nullable|string|max:500',
            'transfer_proof' => 'nullable|image|max:5120',
        ]);

        if (! $withdrawal->isApproved()) {
            return response()->json(['success' => false, 'message' => 'Permintaan harus dalam status disetujui sebelum diselesaikan.'], 422);
        }

        $proofPath = null;
        if ($request->hasFile('transfer_proof')) {
            $proofPath = $request->file('transfer_proof')->store('withdrawal-proofs', 'public');
        }

        try {
            app(TenantWalletService::class)->debitForWithdrawal($withdrawal);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $withdrawal->update([
            'status'         => 'settled',
            'admin_notes'    => $request->admin_notes,
            'transfer_proof' => $proofPath,
            'processed_by'   => auth()->id(),
            'processed_at'   => now(),
        ]);

        $this->logActivity('settled', 'WithdrawalRequest', $withdrawal->id, $withdrawal->request_number . ' — Rp ' . number_format($withdrawal->amount, 0, ',', '.'));

        return response()->json(['success' => true, 'message' => 'Penarikan berhasil diselesaikan dan saldo tenant telah didebit.']);
    }
}
