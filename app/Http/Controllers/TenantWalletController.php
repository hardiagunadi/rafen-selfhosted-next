<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\TenantWallet;
use App\Models\TenantWalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class TenantWalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        $ownerId  = $user->effectiveOwnerId();
        $settings = \App\Models\TenantSettings::where('user_id', $ownerId)->first();

        if (! $settings?->isUsingPlatformGateway()) {
            abort(403, 'Fitur wallet hanya tersedia untuk tenant yang menggunakan Platform Gateway.');
        }

        $wallet  = TenantWallet::getOrCreate($ownerId);
        $primaryBankAccount = BankAccount::where('user_id', $ownerId)->where('is_primary', true)->first()
            ?? BankAccount::where('user_id', $ownerId)->where('is_active', true)->first();

        $recentTransactions = TenantWalletTransaction::where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $pendingWithdrawal = WithdrawalRequest::where('owner_id', $ownerId)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        return view('wallet.index', compact('wallet', 'primaryBankAccount', 'recentTransactions', 'pendingWithdrawal'));
    }

    public function transactionsDatatable(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();

        $query = TenantWalletTransaction::where('owner_id', $ownerId)->orderByDesc('created_at');

        $total    = $query->count();
        $filtered = $total;

        $transactions = $query
            ->skip($request->input('start', 0))
            ->take($request->input('length', 10))
            ->get();

        $data = $transactions->map(fn ($t) => [
            'id'           => $t->id,
            'created_at'   => $t->created_at->format('d/m/Y H:i'),
            'type'         => $t->type,
            'amount'       => 'Rp ' . number_format($t->amount, 0, ',', '.'),
            'fee_deducted' => $t->fee_deducted > 0 ? 'Rp ' . number_format($t->fee_deducted, 0, ',', '.') : '-',
            'balance_after' => 'Rp ' . number_format($t->balance_after, 0, ',', '.'),
            'description'  => $t->description,
        ]);

        return response()->json([
            'draw'            => $request->input('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();
        $wallet  = TenantWallet::getOrCreate($ownerId);

        $validated = $request->validate([
            'amount'              => 'required|numeric|min:10000|max:' . $wallet->balance,
            'bank_name'           => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_name'   => 'required|string|max:100',
        ], [
            'amount.max' => 'Jumlah penarikan melebihi saldo yang tersedia (Rp ' . number_format($wallet->balance, 0, ',', '.') . ').',
            'amount.min' => 'Jumlah penarikan minimal Rp 10.000.',
        ]);

        // Cegah pengajuan berulang saat ada yang masih pending/approved
        $hasPending = WithdrawalRequest::where('owner_id', $ownerId)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'Anda masih memiliki permintaan penarikan yang belum selesai diproses. Silakan tunggu hingga disetujui atau ditolak.');
        }

        WithdrawalRequest::create([
            'request_number'      => WithdrawalRequest::generateRequestNumber(),
            'owner_id'            => $ownerId,
            'amount'              => $validated['amount'],
            'status'              => 'pending',
            'bank_name'           => $validated['bank_name'],
            'bank_account_number' => $validated['bank_account_number'],
            'bank_account_name'   => $validated['bank_account_name'],
        ]);

        return back()->with('success', 'Permintaan penarikan berhasil diajukan. Tim kami akan memproses dalam 1-3 hari kerja.');
    }

    public function withdrawalIndex(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        return view('wallet.withdrawals');
    }

    public function withdrawalDatatable(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();

        $query = WithdrawalRequest::where('owner_id', $ownerId)->orderByDesc('created_at');

        $total    = $query->count();
        $filtered = $total;

        $requests = $query
            ->skip($request->input('start', 0))
            ->take($request->input('length', 10))
            ->get();

        $data = $requests->map(fn ($wr) => [
            'id'             => $wr->id,
            'request_number' => $wr->request_number,
            'amount'         => 'Rp ' . number_format($wr->amount, 0, ',', '.'),
            'status'         => $wr->status,
            'bank_info'      => $wr->bank_name . ' - ' . $wr->bank_account_number . ' (' . $wr->bank_account_name . ')',
            'created_at'     => $wr->created_at->format('d/m/Y H:i'),
            'processed_at'   => $wr->processed_at?->format('d/m/Y H:i') ?? '-',
        ]);

        return response()->json([
            'draw'            => $request->input('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }
}
