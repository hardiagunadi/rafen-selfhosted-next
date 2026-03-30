<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\TenantWallet;
use App\Models\TenantWalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

class TenantWalletService
{
    /**
     * Credit tenant wallet setelah invoice payment via platform gateway berhasil dibayar.
     * Menghitung fee dari platform_fee_percent gateway, lalu credit net amount ke wallet.
     */
    public function creditFromInvoicePayment(Payment $payment): TenantWalletTransaction
    {
        $payment->load(['invoice', 'gateway']);

        $invoice = $payment->invoice;
        $gateway = $payment->gateway;

        if (! $invoice) {
            throw new \RuntimeException("Payment #{$payment->id} tidak memiliki invoice.");
        }

        if (! $gateway) {
            throw new \RuntimeException("Payment #{$payment->id} tidak memiliki payment gateway.");
        }

        $grossAmount = (float) $payment->total_amount;
        $feeAmount   = $gateway->calculateFeeAmount($grossAmount);
        $netAmount   = $gateway->calculateTenantNetAmount($grossAmount);
        $ownerId     = $invoice->owner_id;

        return DB::transaction(function () use ($payment, $gateway, $grossAmount, $feeAmount, $netAmount, $ownerId): TenantWalletTransaction {
            $wallet = TenantWallet::lockForUpdate()->where('owner_id', $ownerId)->first();

            if (! $wallet) {
                $wallet = TenantWallet::create([
                    'owner_id'        => $ownerId,
                    'balance'         => 0,
                    'total_credited'  => 0,
                    'total_withdrawn' => 0,
                ]);
                $wallet = TenantWallet::lockForUpdate()->find($wallet->id);
            }

            $feePercent = (float) $gateway->platform_fee_percent;
            $description = "Invoice #{$payment->invoice->invoice_number}" .
                ($feePercent > 0 ? " — Fee platform {$feePercent}%" : '');

            $txn = $wallet->credit(
                $netAmount,
                $feeAmount,
                $description,
                Payment::class,
                $payment->id
            );

            $payment->update([
                'platform_fee_amount'  => $feeAmount,
                'tenant_net_amount'    => $netAmount,
                'wallet_transaction_id' => $txn->id,
            ]);

            return $txn;
        });
    }

    /**
     * Debit tenant wallet saat withdrawal request di-settle oleh super admin.
     */
    public function debitForWithdrawal(WithdrawalRequest $withdrawalRequest): TenantWalletTransaction
    {
        return DB::transaction(function () use ($withdrawalRequest): TenantWalletTransaction {
            $wallet = TenantWallet::lockForUpdate()->where('owner_id', $withdrawalRequest->owner_id)->firstOrFail();

            return $wallet->debit(
                (float) $withdrawalRequest->amount,
                "Penarikan #{$withdrawalRequest->request_number}",
                WithdrawalRequest::class,
                $withdrawalRequest->id
            );
        });
    }
}
