<?php

namespace App\Services;

use App\Models\FinanceExpense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceNote;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinanceReportService
{
    public const DEFAULT_BHP_RATE_PERCENT = 0.5;

    public const DEFAULT_USO_RATE_PERCENT = 1.25;

    /**
     * @param array{
     *     report:string,
     *     tipe_user:string,
     *     service_type:string,
     *     owner_id:int|null,
     *     teknisi_id:int|null,
     *     date:string,
     *     start_date:string,
     *     end_date:string,
     *     bhp_rate:float|int|string,
     *     uso_rate:float|int|string,
     *     bad_debt_deduction:float|int|string,
     *     interconnection_deduction:float|int|string
     * } $filters
     * @return array{
     *     total:float,
     *     currency:string,
     *     items:Collection<int, array<string, mixed>>,
     *     summary:array<string, float>,
     *     period:array{start:string, end:string, label:string}
     * }
     */
    public function build(User $authUser, array $filters): array
    {
        $reportType = $filters['report'];
        [$periodStart, $periodEnd] = $this->resolvePeriod($filters);
        $ownerId = $this->resolveOwnerId($authUser, $filters['owner_id']);

        $incomeItems = $this->collectIncomeItems(
            authUser: $authUser,
            ownerId: $ownerId,
            teknisiId: $filters['teknisi_id'] ?? null,
            tipeUser: $filters['tipe_user'],
            serviceType: $filters['service_type'],
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );

        $grossRevenue = (float) $incomeItems->sum('amount');

        $bhpUso = $this->calculateBhpUso(
            grossRevenue: $grossRevenue,
            bhpRate: (float) $filters['bhp_rate'],
            usoRate: (float) $filters['uso_rate'],
            badDebtDeduction: (float) $filters['bad_debt_deduction'],
            interconnectionDeduction: (float) $filters['interconnection_deduction'],
        );

        $expenseItems = in_array($reportType, ['expense', 'profit_loss'], true)
            ? $this->collectExpenseItems(
                authUser: $authUser,
                ownerId: $ownerId,
                teknisiId: $filters['teknisi_id'] ?? null,
                serviceType: $filters['service_type'],
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                bhpUso: $bhpUso
            )
            : collect();

        $gatewayExpense = (float) $expenseItems
            ->where('expense_type', 'gateway_fee')
            ->sum('amount');
        $manualExpense = (float) $expenseItems
            ->where('expense_type', 'manual')
            ->sum('amount');
        $operationalExpense = $gatewayExpense + $manualExpense;
        $expenseTotal = (float) $expenseItems->sum('amount');

        $summary = match ($reportType) {
            'expense' => [
                'total_expense' => $expenseTotal,
                'gateway_expense' => $gatewayExpense,
                'manual_expense' => $manualExpense,
                'operational_expense' => $operationalExpense,
                'bhp_amount' => $bhpUso['bhp_amount'],
                'uso_amount' => $bhpUso['uso_amount'],
            ],
            'profit_loss' => [
                'gross_revenue' => $grossRevenue,
                'bad_debt_deduction' => $bhpUso['bad_debt_deduction'],
                'interconnection_deduction' => $bhpUso['interconnection_deduction'],
                'revenue_basis' => $bhpUso['revenue_basis'],
                'gateway_expense' => $gatewayExpense,
                'manual_expense' => $manualExpense,
                'operational_expense' => $operationalExpense,
                'bhp_amount' => $bhpUso['bhp_amount'],
                'uso_amount' => $bhpUso['uso_amount'],
                'total_expense' => $expenseTotal,
                'net_profit' => $grossRevenue - $expenseTotal,
            ],
            'bhp_uso' => [
                'gross_revenue' => $grossRevenue,
                'bad_debt_deduction' => $bhpUso['bad_debt_deduction'],
                'interconnection_deduction' => $bhpUso['interconnection_deduction'],
                'deduction_total' => $bhpUso['deduction_total'],
                'revenue_basis' => $bhpUso['revenue_basis'],
                'bhp_rate' => $bhpUso['bhp_rate'],
                'uso_rate' => $bhpUso['uso_rate'],
                'bhp_amount' => $bhpUso['bhp_amount'],
                'uso_amount' => $bhpUso['uso_amount'],
                'total_obligation' => $bhpUso['total_obligation'],
            ],
            default => [
                'total_income' => $grossRevenue,
                'customer_income' => (float) $incomeItems->where('user_type', 'customer')->sum('amount'),
                'voucher_income' => (float) $incomeItems->where('user_type', 'voucher')->sum('amount'),
            ],
        };

        $items = match ($reportType) {
            'expense' => $expenseItems,
            default => $incomeItems,
        };

        $mainTotal = match ($reportType) {
            'expense' => $summary['total_expense'],
            'profit_loss' => $summary['net_profit'],
            'bhp_uso' => $summary['total_obligation'],
            default => $summary['total_income'],
        };

        return [
            'total' => $mainTotal,
            'currency' => 'IDR',
            'items' => $items,
            'summary' => $summary,
            'period' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'label' => $periodStart->translatedFormat('d M Y').' - '.$periodEnd->translatedFormat('d M Y'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0:Carbon, 1:Carbon}
     */
    private function resolvePeriod(array $filters): array
    {
        if ($filters['report'] === 'daily') {
            $date = Carbon::parse((string) $filters['date']);

            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        $startDate = Carbon::parse((string) $filters['start_date'])->startOfDay();
        $endDate = Carbon::parse((string) $filters['end_date'])->endOfDay();

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    private function resolveOwnerId(User $authUser, ?int $requestedOwnerId): ?int
    {
        if (! $authUser->isSuperAdmin()) {
            return $authUser->effectiveOwnerId();
        }

        return $requestedOwnerId ?: null;
    }

    private function collectIncomeItems(
        User $authUser,
        ?int $ownerId,
        ?int $teknisiId,
        string $tipeUser,
        string $serviceType,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        $includeCustomerIncome = $tipeUser !== 'voucher' && $serviceType !== 'voucher';
        $includeVoucherIncome = $tipeUser !== 'customer' && ($serviceType === '' || $serviceType === 'voucher');

        $items = collect();

        if ($includeCustomerIncome) {
            $invoiceItems = $this->collectInvoiceIncomeItems(
                authUser: $authUser,
                ownerId: $ownerId,
                teknisiId: $teknisiId,
                serviceType: $serviceType,
                periodStart: $periodStart,
                periodEnd: $periodEnd
            );
            $items = $items->concat($invoiceItems);

            $serviceNoteItems = $this->collectServiceNoteIncomeItems(
                authUser: $authUser,
                ownerId: $ownerId,
                teknisiId: $teknisiId,
                serviceType: $serviceType,
                periodStart: $periodStart,
                periodEnd: $periodEnd
            );
            $items = $items->concat($serviceNoteItems);
        }

        if ($includeVoucherIncome) {
            $voucherItems = $this->collectVoucherIncomeItems(
                authUser: $authUser,
                ownerId: $ownerId,
                teknisiId: $teknisiId,
                periodStart: $periodStart,
                periodEnd: $periodEnd
            );
            $items = $items->concat($voucherItems);
        }

        return $items
            ->sortByDesc('timestamp')
            ->values()
            ->map(function (array $item): array {
                unset($item['timestamp']);

                return $item;
            });
    }

    private function collectInvoiceIncomeItems(
        User $authUser,
        ?int $ownerId,
        ?int $teknisiId,
        string $serviceType,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        $query = Invoice::query()
            ->with('owner:id,name')
            ->where('status', 'paid');

        if (! $authUser->isSuperAdmin()) {
            $query->where('owner_id', $authUser->effectiveOwnerId());
        } elseif ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        if (in_array($serviceType, ['pppoe', 'hotspot'], true)) {
            $query->where('tipe_service', $serviceType);
        }
        if ($teknisiId !== null) {
            $query->where('paid_by', $teknisiId);
        }

        $this->applyPaidDateRange(
            query: $query,
            paidAtColumn: 'paid_at',
            fallbackColumn: 'updated_at',
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );

        return $query->get()->map(function (Invoice $invoice): array {
            $paidTime = $invoice->paid_at ?? $invoice->updated_at ?? $invoice->created_at;

            return [
                'time' => $paidTime?->format('d/m/Y H:i') ?? '-',
                'timestamp' => $paidTime?->timestamp ?? 0,
                'user_type' => 'customer',
                'service' => (string) ($invoice->tipe_service ?? 'pppoe'),
                'owner' => (string) ($invoice->owner?->name ?? '-'),
                'reference' => (string) $invoice->invoice_number,
                'amount' => (float) $invoice->total,
            ];
        });
    }

    private function collectVoucherIncomeItems(
        User $authUser,
        ?int $ownerId,
        ?int $teknisiId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        if ($teknisiId !== null) {
            return collect();
        }

        $query = Transaction::query()
            ->with('owner:id,name')
            ->where('status', 'paid')
            ->where('type', 'voucher');

        if (! $authUser->isSuperAdmin()) {
            $query->where('owner_id', $authUser->effectiveOwnerId());
        } elseif ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        $this->applyPaidDateRange(
            query: $query,
            paidAtColumn: 'paid_at',
            fallbackColumn: 'created_at',
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );

        return $query->get()->map(function (Transaction $transaction): array {
            $paidTime = $transaction->paid_at ?? $transaction->created_at;

            return [
                'time' => $paidTime?->format('d/m/Y H:i') ?? '-',
                'timestamp' => $paidTime?->timestamp ?? 0,
                'user_type' => 'voucher',
                'service' => 'voucher',
                'owner' => (string) ($transaction->owner?->name ?? '-'),
                'reference' => (string) ($transaction->mixradius_id ?: 'TRX-'.$transaction->id),
                'amount' => (float) $transaction->total,
            ];
        });
    }

    private function collectServiceNoteIncomeItems(
        User $authUser,
        ?int $ownerId,
        ?int $teknisiId,
        string $serviceType,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        $query = ServiceNote::query()
            ->with('owner:id,name')
            ->where('status', 'paid');

        if (! $authUser->isSuperAdmin()) {
            $query->where('owner_id', $authUser->effectiveOwnerId());
        } elseif ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        if (in_array($serviceType, ['pppoe', 'hotspot'], true)) {
            $query->where('service_type', $serviceType);
        }

        if ($teknisiId !== null) {
            $query->where('paid_by', $teknisiId);
        }

        $this->applyPaidDateRange(
            query: $query,
            paidAtColumn: 'paid_at',
            fallbackColumn: 'created_at',
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );

        return $query->get()->map(function (ServiceNote $serviceNote): array {
            $paidTime = $serviceNote->paid_at ?? $serviceNote->created_at;

            return [
                'time' => $paidTime?->format('d/m/Y H:i') ?? '-',
                'timestamp' => $paidTime?->timestamp ?? 0,
                'user_type' => 'customer',
                'service' => (string) ($serviceNote->service_type ?: 'general'),
                'owner' => (string) ($serviceNote->owner?->name ?? '-'),
                'reference' => (string) $serviceNote->document_number,
                'amount' => (float) $serviceNote->total,
            ];
        });
    }

    /**
     * @param  array<string, float>  $bhpUso
     */
    private function collectExpenseItems(
        User $authUser,
        ?int $ownerId,
        ?int $teknisiId,
        string $serviceType,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $bhpUso,
    ): Collection {
        $query = Payment::query()
            ->with('invoice.owner:id,name')
            ->where('payment_type', 'invoice')
            ->where('status', 'paid')
            ->where('fee', '>', 0);

        if (! $authUser->isSuperAdmin()) {
            $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('owner_id', $authUser->effectiveOwnerId()));
        } elseif ($ownerId !== null) {
            $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('owner_id', $ownerId));
        }

        if (in_array($serviceType, ['pppoe', 'hotspot'], true)) {
            $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('tipe_service', $serviceType));
        }
        if ($teknisiId !== null) {
            $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('paid_by', $teknisiId));
        }

        $this->applyPaidDateRange(
            query: $query,
            paidAtColumn: 'paid_at',
            fallbackColumn: 'updated_at',
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );

        $items = $query->get()->map(function (Payment $payment): array {
            $paidTime = $payment->paid_at ?? $payment->updated_at ?? $payment->created_at;
            $invoice = $payment->invoice;
            $channel = strtoupper((string) ($payment->payment_channel ?? '-'));

            return [
                'time' => $paidTime?->format('d/m/Y H:i') ?? '-',
                'timestamp' => $paidTime?->timestamp ?? 0,
                'expense_type' => 'gateway_fee',
                'category' => 'Biaya Payment Gateway',
                'description' => trim('Invoice '.($invoice?->invoice_number ?? '-').' / '.$channel),
                'owner' => (string) ($invoice?->owner?->name ?? '-'),
                'amount' => (float) $payment->fee,
            ];
        })->values();

        $manualItems = $this->collectManualExpenseItems(
            authUser: $authUser,
            ownerId: $ownerId,
            teknisiId: $teknisiId,
            serviceType: $serviceType,
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );

        $ownerLabel = $ownerId !== null
            ? (string) (User::query()->find($ownerId)?->name ?? '-')
            : 'Semua Owner';

        $regulatoryRows = collect([
            [
                'time' => $periodEnd->format('d/m/Y H:i'),
                'timestamp' => $periodEnd->timestamp,
                'expense_type' => 'bhp',
                'category' => 'Estimasi BHP Telekomunikasi',
                'description' => 'Tarif '.$this->formatPercent($bhpUso['bhp_rate']).' dari dasar pengenaan',
                'owner' => $ownerLabel,
                'amount' => $bhpUso['bhp_amount'],
            ],
            [
                'time' => $periodEnd->format('d/m/Y H:i'),
                'timestamp' => $periodEnd->timestamp,
                'expense_type' => 'uso',
                'category' => 'Estimasi Kontribusi USO',
                'description' => 'Tarif '.$this->formatPercent($bhpUso['uso_rate']).' dari dasar pengenaan',
                'owner' => $ownerLabel,
                'amount' => $bhpUso['uso_amount'],
            ],
        ]);

        return $items
            ->concat($manualItems)
            ->concat($regulatoryRows)
            ->sortByDesc('timestamp')
            ->values()
            ->map(function (array $item): array {
                unset($item['timestamp']);

                return $item;
            });
    }

    private function collectManualExpenseItems(
        User $authUser,
        ?int $ownerId,
        ?int $teknisiId,
        string $serviceType,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        if ($teknisiId !== null) {
            return collect();
        }

        $query = FinanceExpense::query()
            ->with('owner:id,name')
            ->accessibleBy($authUser)
            ->whereBetween('expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        if ($authUser->isSuperAdmin() && $ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        if (in_array($serviceType, ['pppoe', 'hotspot', 'voucher'], true)) {
            $query->whereIn('service_type', ['general', $serviceType]);
        }

        return $query->get()->map(function (FinanceExpense $expense): array {
            $expenseTimestamp = $expense->expense_date?->copy()->endOfDay()->timestamp ?? 0;
            $serviceLabel = strtoupper((string) $expense->service_type);
            $paymentMethod = $expense->payment_method ? strtoupper((string) $expense->payment_method) : null;
            $reference = $expense->reference ? 'Ref: '.$expense->reference : null;

            return [
                'time' => $expense->expense_date?->format('d/m/Y').' 23:59' ?? '-',
                'timestamp' => $expenseTimestamp,
                'expense_type' => 'manual',
                'category' => 'Pengeluaran Manual - '.$expense->category,
                'description' => trim(collect([$serviceLabel, $paymentMethod, $reference, $expense->description])->filter()->implode(' | ')),
                'owner' => (string) ($expense->owner?->name ?? '-'),
                'amount' => (float) $expense->amount,
            ];
        });
    }

    /**
     * @return array{
     *     bhp_rate:float,
     *     uso_rate:float,
     *     bad_debt_deduction:float,
     *     interconnection_deduction:float,
     *     deduction_total:float,
     *     revenue_basis:float,
     *     bhp_amount:float,
     *     uso_amount:float,
     *     total_obligation:float
     * }
     */
    private function calculateBhpUso(
        float $grossRevenue,
        float $bhpRate,
        float $usoRate,
        float $badDebtDeduction,
        float $interconnectionDeduction,
    ): array {
        $normalizedBhpRate = max(0.0, $bhpRate);
        $normalizedUsoRate = max(0.0, $usoRate);
        $normalizedBadDebt = max(0.0, $badDebtDeduction);
        $normalizedInterconnection = max(0.0, $interconnectionDeduction);

        $deductionTotal = $normalizedBadDebt + $normalizedInterconnection;
        $revenueBasis = max(0.0, $grossRevenue - $deductionTotal);
        $bhpAmount = round($revenueBasis * $normalizedBhpRate / 100, 2);
        $usoAmount = round($revenueBasis * $normalizedUsoRate / 100, 2);
        $totalObligation = $bhpAmount + $usoAmount;

        return [
            'bhp_rate' => $normalizedBhpRate,
            'uso_rate' => $normalizedUsoRate,
            'bad_debt_deduction' => $normalizedBadDebt,
            'interconnection_deduction' => $normalizedInterconnection,
            'deduction_total' => $deductionTotal,
            'revenue_basis' => $revenueBasis,
            'bhp_amount' => $bhpAmount,
            'uso_amount' => $usoAmount,
            'total_obligation' => $totalObligation,
        ];
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%';
    }

    private function applyPaidDateRange(
        Builder $query,
        string $paidAtColumn,
        string $fallbackColumn,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Builder {
        return $query->where(function (Builder $innerQuery) use ($paidAtColumn, $fallbackColumn, $periodStart, $periodEnd): void {
            $innerQuery->whereBetween($paidAtColumn, [$periodStart, $periodEnd])
                ->orWhere(function (Builder $fallbackQuery) use ($paidAtColumn, $fallbackColumn, $periodStart, $periodEnd): void {
                    $fallbackQuery->whereNull($paidAtColumn)
                        ->whereBetween($fallbackColumn, [$periodStart, $periodEnd]);
                });
        });
    }
}
