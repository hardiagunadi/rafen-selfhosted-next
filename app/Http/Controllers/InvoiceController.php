<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaidInvoiceSideEffectsJob;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use App\Services\WaGatewayService;
use App\Services\WaNotificationService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        return $this->renderInvoiceIndex(
            request: $request,
            pageTitle: 'Data Tagihan',
            tableHeading: 'Data Tagihan',
            pageDescription: 'Rekap invoice terhutang per bulan dan data tagihan lengkap.',
            showMonthlyDebtRecap: true,
            initialStatusFilter: null,
            initialInvoiceContext: null,
        );
    }

    public function unpaidIndex(Request $request): View
    {
        return $this->renderInvoiceIndex(
            request: $request,
            pageTitle: 'Invoice Belum Lunas',
            tableHeading: 'Invoice Belum Lunas',
            pageDescription: 'Khusus untuk perpanjangan bulan berjalan dan invoice tunggakan yang masih belum lunas.',
            showMonthlyDebtRecap: false,
            initialStatusFilter: 'unpaid',
            initialInvoiceContext: 'current',
        );
    }

    public function datatable(Request $request): JsonResponse
    {
        $user = $request->user();
        $isTeknisi = $user->role === 'teknisi';
        $search = $request->input('search.value', '');
        $statusFilter = (string) $request->input('status', '');
        $dueMonthFilter = $this->normalizeDueMonthFilter((string) $request->input('due_month', ''));
        $invoiceContextFilter = $this->normalizeInvoiceContextFilter((string) $request->input('invoice_context', ''));

        $query = $this->applyDatatableStatusFilter(
            Invoice::query()
                ->with(['pppUser.profile', 'owner'])
                ->withCount(['payments as pending_count' => fn ($q) => $q->where('status', 'pending')])
                ->accessibleBy($user),
            $statusFilter
        )
            ->when($dueMonthFilter !== null, function (Builder $query) use ($dueMonthFilter): void {
                $query->whereYear('due_date', (int) substr($dueMonthFilter, 0, 4))
                    ->whereMonth('due_date', (int) substr($dueMonthFilter, 5, 2));
            })
            ->when($invoiceContextFilter !== null, fn (Builder $query) => $this->applyInvoiceContextFilter($query, $invoiceContextFilter))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total = Invoice::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        $this->enforceOverdueIsolationForInvoices($rows);

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(function (Invoice $r) use ($isTeknisi): array {
                [$statusLabel, $statusVariant] = $this->resolveInvoiceStatusDisplay($r);
                [$contextLabel, $contextVariant] = $this->resolveInvoiceContextDisplay($r);
                $isRenewedWithoutPayment = $r->status === 'unpaid' && (bool) $r->renewed_without_payment;
                $canMarkAsPaid = $r->status === 'unpaid' && ($r->pending_count ?? 0) === 0 && $isRenewedWithoutPayment;

                return [
                    'id' => $r->id,
                    'invoice_number' => $r->invoice_number,
                    'customer_id' => $r->customer_id ?? '-',
                    'customer_name' => $r->customer_name ?? '-',
                    'tipe_service' => strtoupper(str_replace('_', '/', $r->tipe_service ?? '')),
                    'paket_langganan' => $r->paket_langganan ?? '-',
                    'total' => number_format($r->total, 0, ',', '.'),
                    'due_date' => $r->due_date?->format('d-m-Y') ?? '-',
                    'owner_name' => $r->owner?->name ?? '-',
                    'status' => $r->status,
                    'status_label' => $statusLabel,
                    'status_variant' => $statusVariant,
                    'invoice_context_label' => $contextLabel,
                    'invoice_context_variant' => $contextVariant,
                    'has_pending' => ($r->pending_count ?? 0) > 0,
                    'can_pay' => $r->status === 'unpaid' && ($r->pending_count ?? 0) === 0 && ! $isRenewedWithoutPayment,
                    'can_mark_paid' => $canMarkAsPaid,
                    'can_renew' => $r->status === 'unpaid' && ! $isRenewedWithoutPayment,
                    'can_nota' => ! $isTeknisi,
                    'can_delete' => ! $isTeknisi,
                    'pay_url' => route('invoices.pay', $r->id),
                    'renew_url' => route('invoices.renew', $r->id),
                    'destroy_url' => route('invoices.destroy', $r->id),
                    'show_url' => route('invoices.show', $r->id),
                    'print_url' => route('invoices.print', $r->id),
                    'nota_url' => route('invoices.nota', $r->id),
                    'is_nota_printed' => $r->nota_printed_at !== null,
                    'nota_printed_at' => $r->nota_printed_at?->translatedFormat('d M Y H:i'),
                ];
            }),
        ]);
    }

    private function applyDatatableStatusFilter(Builder $query, string $statusFilter): Builder
    {
        return match ($statusFilter) {
            'paid' => $query->where('status', 'paid'),
            'unpaid' => $query->where('status', 'unpaid'),
            'active_unpaid' => $query->where('status', 'unpaid')
                ->whereHas('pppUser', fn (Builder $pppUserQuery) => $pppUserQuery->where('status_akun', 'enable')),
            'isolated_unpaid' => $query->where('status', 'unpaid')
                ->whereHas('pppUser', fn (Builder $pppUserQuery) => $pppUserQuery->where('status_akun', 'isolir')),
            default => $query,
        };
    }

    private function normalizeDueMonthFilter(string $dueMonth): ?string
    {
        return preg_match('/^\d{4}-\d{2}$/', $dueMonth) === 1
            ? $dueMonth
            : null;
    }

    private function normalizeInvoiceContextFilter(string $invoiceContext): ?string
    {
        return in_array($invoiceContext, ['historical', 'current'], true)
            ? $invoiceContext
            : null;
    }

    private function applyInvoiceContextFilter(Builder $query, string $invoiceContextFilter): Builder
    {
        $today = now()->toDateString();
        $currentMonth = now()->format('Y-m');
        $currentMonthStart = now()->startOfMonth()->toDateString();
        $invoiceDueMonthExpression = $this->yearMonthExpression('invoices.due_date');

        $query->where('status', 'unpaid');

        if ($invoiceContextFilter === 'historical') {
            return $query->where(function (Builder $historicalQuery) use ($currentMonthStart, $today): void {
                $historicalQuery
                    ->whereDate('invoices.due_date', '<', $currentMonthStart)
                    ->whereHas('pppUser', function (Builder $pppUserQuery) use ($today): void {
                        $pppUserQuery->where(function (Builder $staleDueDateQuery) use ($today): void {
                            $staleDueDateQuery
                                ->whereNull('ppp_users.jatuh_tempo')
                                ->orWhereDate('ppp_users.jatuh_tempo', '<=', $today);
                        });
                    })
                    ->orWhereHas('pppUser', function (Builder $pppUserQuery) use ($today): void {
                        $pppUserQuery
                            ->whereDate('ppp_users.jatuh_tempo', '>', $today)
                            ->whereRaw("{$this->yearMonthExpression('invoices.due_date')} < {$this->yearMonthExpression('ppp_users.jatuh_tempo')}");
                    });
            });
        }

        return $query->whereHas('pppUser', function (Builder $pppUserQuery) use ($today, $currentMonth, $invoiceDueMonthExpression): void {
            $pppUserQuery
                ->where(function (Builder $futureDueQuery) use ($today): void {
                    $futureDueQuery
                        ->whereDate('ppp_users.jatuh_tempo', '>', $today)
                        ->whereRaw("{$this->yearMonthExpression('invoices.due_date')} = {$this->yearMonthExpression('ppp_users.jatuh_tempo')}");
                })
                ->orWhere(function (Builder $activeMonthQuery) use ($today, $currentMonth, $invoiceDueMonthExpression): void {
                    $activeMonthQuery
                        ->where(function (Builder $staleDueDateQuery) use ($today): void {
                            $staleDueDateQuery
                                ->whereNull('ppp_users.jatuh_tempo')
                                ->orWhereDate('ppp_users.jatuh_tempo', '<=', $today);
                        })
                        ->whereRaw("{$invoiceDueMonthExpression} = ?", [$currentMonth]);
                });
        });
    }

    private function yearMonthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function renderInvoiceIndex(
        Request $request,
        string $pageTitle,
        string $tableHeading,
        string $pageDescription,
        bool $showMonthlyDebtRecap,
        ?string $initialStatusFilter,
        ?string $initialInvoiceContext,
    ): View {
        $selectedDueMonth = $showMonthlyDebtRecap
            ? $this->normalizeDueMonthFilter((string) $request->input('due_month', ''))
            : null;
        $selectedInvoiceContext = $this->normalizeInvoiceContextFilter((string) $request->input('invoice_context', ''))
            ?? $initialInvoiceContext;
        $invoiceContextOptions = $this->invoiceContextOptions();

        $unpaidInvoices = Invoice::query()
            ->accessibleBy($request->user())
            ->where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get(['id', 'due_date', 'total']);

        $monthlyDebt = $showMonthlyDebtRecap
            ? $this->buildMonthlyDebtRecap($unpaidInvoices)
            : collect();

        $unpaidSummary = [
            'invoice_count' => $unpaidInvoices->count(),
            'month_count' => $monthlyDebt->count(),
            'total_amount' => (float) $unpaidInvoices->sum('total'),
            'oldest_month_label' => $monthlyDebt->first()['month_label'] ?? '-',
        ];

        $selectedDueMonthLabel = $monthlyDebt
            ->firstWhere('month_key', $selectedDueMonth)['month_label'] ?? null;

        $selectedInvoiceContextLabel = $selectedInvoiceContext !== null
            ? ($invoiceContextOptions[$selectedInvoiceContext] ?? null)
            : null;

        return view('invoices.index', compact(
            'pageTitle',
            'tableHeading',
            'pageDescription',
            'showMonthlyDebtRecap',
            'initialStatusFilter',
            'monthlyDebt',
            'unpaidSummary',
            'selectedDueMonth',
            'selectedDueMonthLabel',
            'selectedInvoiceContext',
            'selectedInvoiceContextLabel',
            'invoiceContextOptions',
        ));
    }

    /**
     * @param  Collection<int, Invoice>  $unpaidInvoices
     * @return Collection<int, array{month_key: string, month_label: string, invoice_count: int, total_amount: float}>
     */
    private function buildMonthlyDebtRecap(Collection $unpaidInvoices): Collection
    {
        return $unpaidInvoices
            ->groupBy(fn (Invoice $invoice): string => $invoice->due_date?->format('Y-m') ?? 'tanpa-jatuh-tempo')
            ->map(function (Collection $group, string $monthKey): array {
                /** @var Invoice|null $firstInvoice */
                $firstInvoice = $group->first();

                return [
                    'month_key' => $monthKey,
                    'month_label' => $firstInvoice?->due_date?->translatedFormat('F Y') ?? 'Tanpa Jatuh Tempo',
                    'invoice_count' => $group->count(),
                    'total_amount' => (float) $group->sum('total'),
                ];
            })
            ->values();
    }

    /**
     * @return array{historical: string, current: string}
     */
    private function invoiceContextOptions(): array
    {
        return [
            'historical' => 'Invoice Tunggakan',
            'current' => 'Perpanjangan Bulan Berjalan',
        ];
    }

    public function show(Invoice $invoice): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser.profile', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();
        $pendingPayment = Payment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return view('invoices.show', compact('invoice', 'bankAccounts', 'settings', 'pendingPayment'));
    }

    public function print(Invoice $invoice): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser.profile', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.print', compact('invoice', 'bankAccounts', 'settings'));
    }

    public function notaBulk(Request $request): View
    {
        $user = auth()->user();
        $ids = array_filter(explode(',', $request->input('ids', '')), 'is_numeric');

        $invoices = Invoice::query()
            ->with(['pppUser', 'owner'])
            ->whereIn('id', $ids)
            ->accessibleBy($user)
            ->get();

        $settings = $invoices->first()?->owner?->getSettings();

        $alreadyPrintedCount = $invoices->filter(fn ($i) => $i->hasBeenNotaPrinted())->count();

        foreach ($invoices as $invoice) {
            if (! $invoice->hasBeenNotaPrinted()) {
                $invoice->update([
                    'nota_printed_at' => now(),
                    'nota_printed_by' => $user->id,
                ]);
                $this->logActivity('nota_printed', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);
            } else {
                $this->logActivity('nota_reprinted', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id, [
                    'context' => 'bulk',
                    'original_printed_at' => $invoice->nota_printed_at->toDateTimeString(),
                ]);
            }
        }

        return view('invoices.nota-bulk', compact('invoices', 'settings', 'alreadyPrintedCount'));
    }

    public function nota(Invoice $invoice, Request $request): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $isReprint = $invoice->hasBeenNotaPrinted();

        if ($isReprint && ! $request->boolean('confirm')) {
            return redirect()->route('invoices.nota.confirm', $invoice->id);
        }

        $invoice->load(['pppUser', 'owner', 'payment', 'paidBy']);

        if (! $isReprint) {
            $invoice->update([
                'nota_printed_at' => now(),
                'nota_printed_by' => $user->id,
            ]);
            $this->logActivity('nota_printed', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);
        } else {
            $this->logActivity('nota_reprinted', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id, [
                'original_printed_at' => $invoice->nota_printed_at->toDateTimeString(),
                'original_printed_by' => $invoice->nota_printed_by,
            ]);
        }

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.nota', compact('invoice', 'bankAccounts', 'settings', 'isReprint'));
    }

    public function notaConfirm(Invoice $invoice): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if (! $invoice->hasBeenNotaPrinted()) {
            return redirect()->route('invoices.nota', $invoice->id);
        }

        $invoice->load(['notaPrintedBy']);

        return view('invoices.nota-confirm', compact('invoice'));
    }

    public function pay(Request $request, Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->isTeknisi()) {
            $linkedCustomer = $invoice->pppUser ?? $invoice->hotspotUser;
            if ($linkedCustomer && $linkedCustomer->assigned_teknisi_id !== null && $linkedCustomer->assigned_teknisi_id !== $user->id) {
                abort(403);
            }
        }

        if ($invoice->status === 'paid') {
            if (request()->wantsJson()) {
                return response()->json(['status' => 'Invoice sudah dibayar.']);
            }

            return redirect()->back()->with('status', 'Invoice sudah dibayar.');
        }

        $paidAt = now();
        $cashReceived = (float) ($request->input('cash_received') ?: 0);
        $hasCashReceived = $cashReceived > 0;
        $wasOnProcess = false;
        $wasIsolir = false;
        $pppUserId = null;

        $invoice->update([
            'status' => 'paid',
            'renewed_without_payment' => false,
            'paid_at' => $paidAt,
            'paid_by' => $user->id,
            'cash_received' => $request->input('cash_received') ?: null,
            'transfer_amount' => $request->input('transfer_amount') ?: null,
            'payment_note' => $request->input('payment_note') ?: null,
        ]);
        if ($invoice->pppUser) {
            $pppUser = $invoice->pppUser;
            $pppUserId = $pppUser->id;
            $wasOnProcess = $pppUser->status_registrasi === 'on_process';
            $wasIsolir = $pppUser->status_akun === 'isolir';

            $pppUser->update([
                'status_bayar' => 'sudah_bayar',
                'status_akun' => 'enable',
                'jatuh_tempo' => $this->extendDueDate($invoice),
            ]);

            if ($wasOnProcess) {
                $pppUser->update(['status_registrasi' => 'aktif']);
            }
        }

        $this->logActivity('paid', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        ProcessPaidInvoiceSideEffectsJob::dispatchAfterResponse(
            invoiceId: (int) $invoice->id,
            ownerId: (int) $invoice->owner_id,
            paidByUserId: (int) $user->id,
            pppUserId: $pppUserId,
            wasOnProcess: $wasOnProcess,
            wasIsolir: $wasIsolir,
            hasCashReceived: $hasCashReceived,
            paidDate: $paidAt->toDateString(),
        );

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Invoice dibayar.']);
        }

        return redirect()->back()->with('status', 'Invoice dibayar.');
    }

    public function renew(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->status === 'paid') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Invoice sudah dibayar.'], 422);
            }

            return redirect()->back()->with('status', 'Invoice sudah dibayar.');
        }

        if ($invoice->renewed_without_payment) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Layanan sudah diperpanjang untuk periode ini.'], 422);
            }

            return redirect()->back()->with('status', 'Layanan sudah diperpanjang untuk periode ini.');
        }

        $newDue = $this->extendDueDate($invoice);
        $invoice->update([
            'due_date' => $newDue,
            'status' => 'unpaid',
            'renewed_without_payment' => true,
        ]);

        if ($invoice->pppUser) {
            $pppUser = $invoice->pppUser;
            $wasIsolir = $pppUser->status_akun === 'isolir';

            $pppUser->update([
                'jatuh_tempo' => $newDue,
                'status_bayar' => 'belum_bayar',
                'status_akun' => 'enable',
            ]);

            try {
                $pppUser->refresh();
                app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

                if ($wasIsolir) {
                    app(IsolirSynchronizer::class)->deisolate($pppUser);
                }
            } catch (\Throwable $exception) {
                Log::warning('Invoice renew side effects failed', [
                    'invoice_id' => $invoice->id,
                    'ppp_user_id' => $pppUser->id,
                    'was_isolir' => $wasIsolir,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logActivity('renewed', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        $settings = TenantSettings::getOrCreate((int) $invoice->owner_id);
        $freshInvoice = $invoice->fresh()->load('pppUser');
        if ($freshInvoice->pppUser) {
            WaNotificationService::notifyInvoiceCreated($settings, $freshInvoice, $freshInvoice->pppUser);
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Layanan diperpanjang. Status: Aktif - Belum Bayar.']);
        }

        return redirect()->back()->with('status', 'Layanan diperpanjang. Status: Aktif - Belum Bayar.');
    }

    public function destroy(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);
        $pppUser = $invoice->pppUser;
        $invoice->delete();

        if ($pppUser && $pppUser->status_bayar !== 'sudah_bayar') {
            $pppUser->update(['status_bayar' => 'belum_bayar']);
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Invoice dihapus.']);
        }

        return redirect()->back()->with('status', 'Invoice dihapus.');
    }

    public function sendWa(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        $canSendWa = $user->isSuperAdmin() || $user->isAdmin() || in_array($user->role, ['keuangan', 'noc', 'it_support', 'cs']);
        if (! $canSendWa) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $settings = TenantSettings::getOrCreate((int) $invoice->owner_id);

        if (! $settings->hasWaConfigured()) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'WhatsApp Gateway belum dikonfigurasi.'], 422);
            }

            return redirect()->back()->with('error', 'WhatsApp Gateway belum dikonfigurasi.');
        }

        $invoice->load('pppUser');

        $pppUser = $invoice->pppUser;
        $phone = $pppUser->nomor_hp ?? '';

        if (! $pppUser || empty(trim($phone))) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Pelanggan tidak ditemukan atau nomor HP tidak tersedia.'], 422);
            }

            return redirect()->back()->with('error', 'Pelanggan tidak ditemukan atau nomor HP tidak tersedia.');
        }

        // Kirim langsung (bypass toggle notifikasi otomatis — ini pengiriman manual admin)
        $waService = WaGatewayService::forTenant($settings);
        if (! $waService) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'WA Gateway tidak dapat diinisialisasi.'], 422);
            }

            return redirect()->back()->with('error', 'WA Gateway tidak dapat diinisialisasi.');
        }

        if ($invoice->isPaid()) {
            $template = $settings->getTemplate('payment');
            $paidAt = $invoice->paid_at ? $invoice->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
            $customerId = $invoice->customer_id ?? ($pppUser->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? ($pppUser->profile?->name ?? '-');
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : strtoupper((string) $pppUser->tipe_service);
            $csNumber = $settings->business_phone ?? '-';
            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{paid_at}', '{customer_id}', '{profile}', '{service}', '{cs_number}'],
                [
                    $invoice->customer_name ?? 'Pelanggan',
                    $invoice->invoice_number,
                    'Rp '.number_format($invoice->total, 0, ',', '.'),
                    $paidAt,
                    $customerId,
                    $profileName,
                    $serviceType,
                    $csNumber,
                ],
                $template
            );
        } else {
            $template = $settings->getTemplate('invoice');
            $customerId = $invoice->customer_id ?? ($pppUser->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? ($pppUser->profile?->name ?? '-');
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : strtoupper((string) $pppUser->tipe_service);
            $csNumber = $settings->business_phone ?? '-';
            $bankAccounts = $invoice->owner?->bankAccounts()->active()->get()
                ?? BankAccount::where('user_id', $invoice->owner_id)->where('is_active', true)->get();
            $bankLines = $bankAccounts->map(fn ($b) => $b->bank_name.' '.$b->account_number.' a/n '.$b->account_name)->join("\n");
            if (empty(trim($bankLines))) {
                $bankLines = '-';
            }

            if (empty($invoice->payment_token)) {
                $invoice->update(['payment_token' => Invoice::generatePaymentToken()]);
            }
            $paymentLink = route('customer.invoice', $invoice->payment_token);

            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{due_date}', '{customer_id}', '{profile}', '{service}', '{cs_number}', '{bank_account}', '{payment_link}'],
                [
                    $invoice->customer_name ?? 'Pelanggan',
                    $invoice->invoice_number,
                    'Rp '.number_format($invoice->total, 0, ',', '.'),
                    $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                    $customerId,
                    $profileName,
                    $serviceType,
                    $csNumber,
                    $bankLines,
                    $paymentLink,
                ],
                $template
            );
        }

        $context = [
            'event' => $invoice->isPaid() ? 'invoice_paid' : 'invoice_created',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'user_id' => $pppUser->id,
            'username' => $pppUser->username,
            'name' => $invoice->customer_name ?? $pppUser->customer_name ?? null,
        ];

        $waService->sendMessage($phone, $message, $context);

        $this->logActivity('send_wa', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Notifikasi WhatsApp berhasil dikirim ke '.$phone]);
        }

        return redirect()->back()->with('status', 'Notifikasi WhatsApp berhasil dikirim ke '.$phone);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function enforceOverdueIsolationForInvoices(Collection $invoices): void
    {
        $today = now()->toDateString();

        $candidates = $invoices
            ->pluck('pppUser')
            ->filter(static fn ($pppUser): bool => $pppUser instanceof PppUser)
            ->unique('id')
            ->filter(static function (PppUser $pppUser) use ($today): bool {
                if ($pppUser->status_akun !== 'enable') {
                    return false;
                }

                if ($pppUser->status_bayar !== 'belum_bayar') {
                    return false;
                }

                if ($pppUser->aksi_jatuh_tempo !== 'isolir') {
                    return false;
                }

                if (! $pppUser->jatuh_tempo) {
                    return false;
                }

                return $pppUser->jatuh_tempo->toDateString() <= $today;
            });

        if ($candidates->isEmpty()) {
            return;
        }

        $settingsCache = [];
        $radiusSync = app(RadiusReplySynchronizer::class);
        $isolirSync = app(IsolirSynchronizer::class);

        foreach ($candidates as $pppUser) {
            $ownerId = (int) $pppUser->owner_id;

            if (! isset($settingsCache[$ownerId])) {
                $settingsCache[$ownerId] = TenantSettings::getOrCreate($ownerId);
            }

            if (! $settingsCache[$ownerId]->auto_isolate_unpaid) {
                continue;
            }

            try {
                $pppUser->update(['status_akun' => 'isolir']);
                $pppUser->refresh();

                $radiusSync->syncSingleUser($pppUser);
                $isolirSync->isolate($pppUser);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveInvoiceStatusDisplay(Invoice $invoice): array
    {
        if ($invoice->status === 'paid') {
            return ['Lunas', 'success'];
        }

        if ($invoice->pppUser?->status_akun === 'isolir') {
            return ['Belum Bayar - Terisolir', 'danger'];
        }

        if ($invoice->status === 'unpaid' && $invoice->pppUser?->status_akun === 'enable') {
            return ['Aktif - Belum Bayar', 'warning'];
        }

        return ['Belum Bayar', 'warning'];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveInvoiceContextDisplay(Invoice $invoice): array
    {
        if ($invoice->status !== 'unpaid') {
            return [null, null];
        }

        $currentDueDate = $invoice->pppUser?->jatuh_tempo?->copy()->endOfDay();

        if ($invoice->isHistoricalUnpaid($currentDueDate)) {
            return [$this->invoiceContextOptions()['historical'], 'secondary'];
        }

        if ($invoice->isCurrentBillingInvoice($currentDueDate)) {
            return [$this->invoiceContextOptions()['current'], 'info'];
        }

        return [null, null];
    }

    private function extendDueDate(Invoice $invoice): Carbon
    {
        $base = $invoice->due_date ? Carbon::parse($invoice->due_date) : now();

        // Jika due_date sudah lewat, hitung dari hari ini agar tidak extend ke masa lalu
        if ($base->isPast()) {
            $base = now();
        }

        return $base->copy()->addMonthNoOverflow()->endOfDay();
    }
}
