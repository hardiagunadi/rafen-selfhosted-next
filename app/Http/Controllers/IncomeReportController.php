<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceReportRequest;
use App\Http\Requests\StoreFinanceExpenseRequest;
use App\Models\FinanceExpense;
use App\Models\User;
use App\Services\FinanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IncomeReportController extends Controller
{
    public function __invoke(FinanceReportRequest $request, FinanceReportService $financeReportService): View
    {
        $authUser = $request->user();
        if (! $authUser->isSuperAdmin() && ! in_array($authUser->role, ['administrator', 'keuangan', 'teknisi'], true)) {
            abort(403);
        }

        $validated = $request->validated();
        $reportType = (string) ($validated['report'] ?? 'daily');

        $pageTitle = match ($reportType) {
            'period' => 'Pendapatan Periode',
            'expense' => 'Pengeluaran',
            'profit_loss' => 'Laba Rugi',
            'bhp_uso' => 'Hitung BHP | USO',
            default => 'Pendapatan Harian',
        };

        $filters = [
            'tipe_user' => (string) ($validated['tipe_user'] ?? 'semua'),
            'service_type' => (string) ($validated['service_type'] ?? ''),
            'owner_id' => $authUser->isSuperAdmin()
                ? ($validated['owner_id'] ?? null)
                : $authUser->effectiveOwnerId(),
            'teknisi_id' => $authUser->role === 'teknisi' ? $authUser->id : null,
            'report' => $reportType,
            'date' => (string) ($validated['date'] ?? now()->toDateString()),
            'start_date' => (string) ($validated['start_date'] ?? now()->startOfMonth()->toDateString()),
            'end_date' => (string) ($validated['end_date'] ?? now()->endOfMonth()->toDateString()),
            'bhp_rate' => (float) ($validated['bhp_rate'] ?? config('finance.bhp_rate_percent', FinanceReportService::DEFAULT_BHP_RATE_PERCENT)),
            'uso_rate' => (float) ($validated['uso_rate'] ?? config('finance.uso_rate_percent', FinanceReportService::DEFAULT_USO_RATE_PERCENT)),
            'bad_debt_deduction' => (float) ($validated['bad_debt_deduction'] ?? 0),
            'interconnection_deduction' => (float) ($validated['interconnection_deduction'] ?? 0),
        ];

        $owners = $authUser->isSuperAdmin()
            ? User::query()->tenants()->orderBy('name')->get()
            : User::query()->where('id', $authUser->effectiveOwnerId())->get();

        $report = $financeReportService->build($authUser, $filters);
        $bhpUsoReference = (array) config('finance.bhp_uso_reference', []);

        return view('reports.income', compact('filters', 'owners', 'report', 'reportType', 'pageTitle', 'bhpUsoReference'));
    }

    public function storeExpense(StoreFinanceExpenseRequest $request): RedirectResponse
    {
        $authUser = $request->user();
        $validated = $request->validated();

        $ownerId = $authUser->isSuperAdmin()
            ? (int) ($validated['owner_id'] ?? 0)
            : $authUser->effectiveOwnerId();

        if ($ownerId <= 0) {
            return redirect()->back()->withErrors([
                'owner_id' => 'Owner wajib dipilih untuk menambahkan pengeluaran.',
            ])->withInput();
        }

        FinanceExpense::query()->create([
            'owner_id' => $ownerId,
            'created_by' => $authUser->id,
            'expense_date' => $validated['expense_date'],
            'category' => $validated['category'],
            'service_type' => $validated['service_type'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->back()->with('status', 'Pengeluaran berhasil ditambahkan.');
    }
}
