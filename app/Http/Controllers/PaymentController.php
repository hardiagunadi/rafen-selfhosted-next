<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TenantSettings;
use App\Services\DuitkuService;
use App\Services\MidtransService;
use App\Services\TripayService;
use App\Services\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private function resolveGateway(TenantSettings $settings): PaymentGatewayInterface
    {
        // Platform gateway mode: gunakan kredensial super admin
        if ($settings->isUsingPlatformGateway()) {
            $gw = $settings->platformPaymentGateway;
            if ($gw) {
                return match ($gw->provider) {
                    'midtrans' => MidtransService::fromGateway($gw),
                    'duitku'   => DuitkuService::fromGateway($gw),
                    default    => TripayService::fromGateway($gw),
                };
            }
        }

        return match ($settings->getActiveGateway()) {
            'midtrans' => MidtransService::forTenant($settings),
            'duitku'   => DuitkuService::forTenant($settings),
            default    => TripayService::forTenant($settings),
        };
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::query();

        if (!$user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        $payments = $query->with(['invoice', 'subscription', 'gateway'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('payments.index', compact('payments'));
    }

    public function show(Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $payment->user_id !== $user->id) {
            abort(403);
        }

        $payment->load(['invoice', 'subscription']);

        return view('payments.show', compact('payment'));
    }

    public function createForInvoice(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        // Check ownership
        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Invoice sudah dibayar.');
        }

        // Get tenant settings
        $settings      = $invoice->owner->getSettings();
        $manualEnabled = (bool) $settings->enable_manual_payment;
        $bankAccounts  = $invoice->owner->bankAccounts()->active()->get();

        if (!$settings->hasPaymentGateway()) {
            // No Tripay — only show manual transfer if enabled
            if (!$manualEnabled || $bankAccounts->isEmpty()) {
                return redirect()->route('invoices.show', $invoice)
                    ->with('error', 'Tidak ada metode pembayaran yang tersedia. Hubungi admin.');
            }
            return view('payments.manual', compact('invoice', 'settings', 'bankAccounts'));
        }

        // Get available channels from active gateway
        $gateway     = $this->resolveGateway($settings);
        $allChannels = $gateway->getPaymentChannels();

        // Filter to only enabled channels
        $enabledCodes = $settings->getEnabledChannels();
        $channels = empty($enabledCodes)
            ? $allChannels
            : array_values(array_filter($allChannels, fn($ch) => in_array($ch['code'], $enabledCodes)));

        // Group channels (Tripay has static groups; other gateways return flat list)
        $groupedChannels = [];
        if ($settings->getActiveGateway() === 'tripay') {
            foreach (TripayService::getChannelGroups() as $groupKey => $group) {
                $groupChannels = array_filter($channels, fn($ch) => in_array($ch['code'], $group['codes']));
                if (!empty($groupChannels)) {
                    $groupedChannels[$groupKey] = [
                        'name'        => $group['name'],
                        'description' => $group['description'],
                        'channels'    => array_values($groupChannels),
                    ];
                }
            }
        } else {
            // For other gateways, group by type (QRIS vs VA)
            $qris = array_values(array_filter($channels, fn($ch) => str_contains(strtolower($ch['type'] ?? $ch['name'] ?? ''), 'qris')));
            $va   = array_values(array_filter($channels, fn($ch) => !str_contains(strtolower($ch['type'] ?? $ch['name'] ?? ''), 'qris')));
            if (!empty($qris)) {
                $groupedChannels['qris'] = ['name' => 'QRIS', 'description' => 'Bayar via QRIS', 'channels' => $qris];
            }
            if (!empty($va)) {
                $groupedChannels['va'] = ['name' => 'Virtual Account', 'description' => 'Transfer ke Virtual Account', 'channels' => $va];
            }
            if (empty($groupedChannels)) {
                $groupedChannels['other'] = ['name' => 'Metode Pembayaran', 'description' => '', 'channels' => $channels];
            }
        }

        return view('payments.create', compact('invoice', 'channels', 'groupedChannels', 'settings', 'manualEnabled', 'bankAccounts'));
    }

    public function storeForInvoice(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Invoice sudah dibayar.');
        }

        $request->validate([
            'payment_channel' => 'required|string',
        ]);

        $settings = $invoice->owner->getSettings();
        $gateway  = $this->resolveGateway($settings);

        $result = $gateway->createInvoicePayment(
            $invoice,
            $request->payment_channel,
            $settings->payment_expiry_hours
        );

        if ($result['success']) {
            $payment = $result['payment'];

            // Tag payment jika menggunakan platform gateway
            if ($settings->isUsingPlatformGateway()) {
                $payment->update([
                    'via_platform_gateway' => true,
                    'payment_gateway_id'   => $settings->platform_payment_gateway_id,
                ]);
            }

            return redirect()->route('payments.show', $payment)
                ->with('success', 'Pembayaran berhasil dibuat. Silakan selesaikan pembayaran Anda.');
        }

        return back()->with('error', $result['message'] ?? 'Gagal membuat pembayaran.');
    }

    public function callback(Request $request)
    {
        Log::info('Payment callback received', $request->all());

        $callbackData = $request->all();
        $merchantRef = $callbackData['merchant_ref'] ?? '';
        $status = $callbackData['status'] ?? '';

        // Find payment by merchant reference
        $payment = Payment::where('merchant_ref', $merchantRef)->first();

        if (!$payment) {
            Log::warning('Payment not found for callback', ['merchant_ref' => $merchantRef]);
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        // Get settings to verify signature — platform gateway atau tenant gateway
        if ($payment->via_platform_gateway && $payment->gateway) {
            $tripay = TripayService::fromGateway($payment->gateway);
        } elseif ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
            $tripay = TripayService::forTenant($settings);
        } else {
            $tripay = TripayService::forSystem();
        }

        // Verify signature
        if (!$tripay->verifyCallback($callbackData)) {
            Log::warning('Invalid callback signature', $callbackData);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if ($status === 'PAID') {
            $payment->markAsPaid($callbackData);
            Log::info('Payment marked as paid', ['payment_id' => $payment->id]);

            if ($payment->payment_type === 'invoice' && $payment->invoice) {
                $invSettings = TenantSettings::getOrCreate((int) $payment->invoice->owner_id);
                WaNotificationService::notifyInvoicePaid($invSettings, $payment->invoice->fresh()->load('pppUser'));
            }
        } elseif ($status === 'EXPIRED') {
            $payment->markAsExpired();
            Log::info('Payment marked as expired', ['payment_id' => $payment->id]);
        } elseif ($status === 'FAILED') {
            $payment->markAsFailed();
            Log::info('Payment marked as failed', ['payment_id' => $payment->id]);
        }

        return response()->json(['success' => true]);
    }

    public function callbackMidtrans(Request $request)
    {
        $callbackData = $request->all();
        Log::info('Midtrans callback received', $callbackData);

        $orderId = $callbackData['order_id'] ?? '';

        $payment = Payment::where('merchant_ref', $orderId)->first();
        if (!$payment) {
            Log::warning('Midtrans: payment not found', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $settings = null;
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
        }

        // Gunakan platform gateway credentials jika via_platform_gateway
        if ($payment->via_platform_gateway && $payment->gateway) {
            $midtrans = MidtransService::fromGateway($payment->gateway);
        } else {
            $midtrans = $settings ? MidtransService::forTenant($settings) : MidtransService::forSystem();
        }

        if (!$midtrans->verifyCallback($callbackData)) {
            Log::warning('Midtrans: invalid signature', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $transactionStatus = $callbackData['transaction_status'] ?? '';
        $fraudStatus       = $callbackData['fraud_status'] ?? 'accept';

        if (in_array($transactionStatus, ['capture', 'settlement']) && $fraudStatus !== 'deny') {
            $payment->markAsPaid($callbackData);
            Log::info('Midtrans: payment marked as paid', ['payment_id' => $payment->id]);

            if ($payment->payment_type === 'invoice' && $payment->invoice) {
                $invSettings = TenantSettings::getOrCreate((int) $payment->invoice->owner_id);
                WaNotificationService::notifyInvoicePaid($invSettings, $payment->invoice->fresh()->load('pppUser'));
            }
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $payment->markAsExpired();
            Log::info('Midtrans: payment expired/cancelled', ['payment_id' => $payment->id]);
        }

        return response()->json(['success' => true]);
    }

    public function callbackDuitku(Request $request)
    {
        // Duitku sends GET when browser is redirected back after payment
        if ($request->isMethod('GET')) {
            $merchantOrderId = $request->query('merchantOrderId');
            $resultCode      = $request->query('resultCode');
            if ($resultCode === '00') {
                return redirect()->route('payment.success')->with('success', 'Pembayaran berhasil.');
            }
            return redirect('/')->with('info', 'Kembali dari halaman pembayaran.');
        }

        $callbackData = $request->all();
        Log::info('Duitku callback received', $callbackData);

        $merchantOrderId = $callbackData['merchantOrderId'] ?? '';
        $resultCode      = $callbackData['resultCode'] ?? '';

        $payment = Payment::where('merchant_ref', $merchantOrderId)->first();
        if (!$payment) {
            Log::warning('Duitku: payment not found', ['merchantOrderId' => $merchantOrderId]);
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $settings = null;
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
        }

        // Gunakan platform gateway credentials jika via_platform_gateway
        if ($payment->via_platform_gateway && $payment->gateway) {
            $duitku = DuitkuService::fromGateway($payment->gateway);
        } else {
            $duitku = $settings ? DuitkuService::forTenant($settings) : DuitkuService::forSystem();
        }

        if (!$duitku->verifyCallback($callbackData)) {
            Log::warning('Duitku: invalid signature', ['merchantOrderId' => $merchantOrderId]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if ($resultCode === '00') {
            $payment->markAsPaid($callbackData);
            Log::info('Duitku: payment marked as paid', ['payment_id' => $payment->id]);

            if ($payment->payment_type === 'invoice' && $payment->invoice && $settings) {
                WaNotificationService::notifyInvoicePaid($settings, $payment->invoice->fresh()->load('pppUser'));
            }
        } elseif (in_array($resultCode, ['01', '02'])) {
            $payment->markAsFailed();
            Log::info('Duitku: payment failed', ['payment_id' => $payment->id, 'resultCode' => $resultCode]);
        }

        return response()->json(['success' => true]);
    }

    public function checkStatus(Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $payment->user_id !== $user->id) {
            abort(403);
        }

        if (!$payment->reference) {
            return response()->json(['status' => $payment->status]);
        }

        // Get settings
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
            $gateway  = $this->resolveGateway($settings);
        } else {
            $gateway = TripayService::forSystem();
        }

        $result = $gateway->getTransactionDetail($payment->reference);

        if ($result['success']) {
            $data = $result['data'];
            $gatewayStatus = $data['status'] ?? '';

            if ($gatewayStatus === 'PAID' && $payment->status !== 'paid') {
                $payment->markAsPaid($data);
            } elseif ($gatewayStatus === 'EXPIRED' && $payment->status !== 'expired') {
                $payment->markAsExpired();
            }
        }

        return response()->json(['status' => $payment->fresh()->status]);
    }

    public function success(Request $request)
    {
        return view('payments.success');
    }

    public function manualForm(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Invoice sudah dibayar.');
        }

        $settings     = $invoice->owner->getSettings();
        $bankAccounts = $invoice->owner->bankAccounts()->active()->get();

        if (!$settings->enable_manual_payment || $bankAccounts->isEmpty()) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pembayaran manual tidak tersedia.');
        }

        return view('payments.manual', compact('invoice', 'settings', 'bankAccounts'));
    }

    public function manualConfirmation(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $settings = $invoice->owner->getSettings();
        if (!$settings->enable_manual_payment) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pembayaran manual tidak tersedia.');
        }

        $request->validate([
            'payment_proof'      => 'required|image|max:5120',
            'bank_account_id'    => 'required|exists:bank_accounts,id',
            'amount_transferred' => 'required|numeric|min:0',
            'transfer_date'      => 'required|date',
            'notes'              => 'nullable|string|max:500',
        ]);

        // Store proof
        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        $payment = Payment::create([
            'payment_number'     => Payment::generatePaymentNumber(),
            'payment_type'       => 'invoice',
            'user_id'            => $invoice->owner_id,
            'invoice_id'         => $invoice->id,
            'payment_method'     => 'bank_transfer',
            'payment_channel'    => 'manual',
            'amount'             => $invoice->total,
            'fee'                => 0,
            'total_amount'       => $invoice->total,
            'status'             => 'pending',
            'merchant_ref'       => 'MANUAL-' . $invoice->id . '-' . time(),
            'payment_proof'      => $path,
            'bank_account_id'    => $request->bank_account_id,
            'amount_transferred' => $request->amount_transferred,
            'transfer_date'      => $request->transfer_date,
            'notes'              => $request->notes,
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Bukti pembayaran berhasil dikirim. Menunggu konfirmasi.');
    }

    public function customerPortal(string $token)
    {
        $invoice = Invoice::where('payment_token', $token)
            ->with(['pppUser', 'owner', 'payment'])
            ->firstOrFail();

        $settings     = $invoice->owner?->getSettings();
        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();

        $channels        = [];
        $groupedChannels = [];

        if ($settings && $settings->hasPaymentGateway() && $invoice->isUnpaid()) {
            try {
                $gateway     = $this->resolveGateway($settings);
                $allChannels = $gateway->getPaymentChannels();
                $enabledCodes = $settings->getEnabledChannels();
                $channels = empty($enabledCodes)
                    ? $allChannels
                    : array_values(array_filter($allChannels, fn($ch) => in_array($ch['code'], $enabledCodes)));

                if ($settings->getActiveGateway() === 'tripay') {
                    foreach (TripayService::getChannelGroups() as $groupKey => $group) {
                        $groupChannels = array_filter($channels, fn($ch) => in_array($ch['code'], $group['codes']));
                        if (!empty($groupChannels)) {
                            $groupedChannels[$groupKey] = ['name' => $group['name'], 'channels' => array_values($groupChannels)];
                        }
                    }
                } else {
                    $qris = array_values(array_filter($channels, fn($ch) => str_contains(strtolower($ch['type'] ?? $ch['name'] ?? ''), 'qris')));
                    $va   = array_values(array_filter($channels, fn($ch) => !str_contains(strtolower($ch['type'] ?? $ch['name'] ?? ''), 'qris')));
                    if (!empty($qris)) $groupedChannels['qris'] = ['name' => 'QRIS', 'channels' => $qris];
                    if (!empty($va))   $groupedChannels['va']   = ['name' => 'Virtual Account', 'channels' => $va];
                    if (empty($groupedChannels) && !empty($channels)) {
                        $groupedChannels['other'] = ['name' => 'Metode Pembayaran', 'channels' => $channels];
                    }
                }
            } catch (\Throwable) {
                // gateway unavailable — lanjut tanpa channel
            }
        }

        return view('customer.invoice', compact('invoice', 'settings', 'bankAccounts', 'groupedChannels', 'token'));
    }

    public function customerManualConfirmation(string $token, Request $request)
    {
        $invoice = Invoice::where('payment_token', $token)->firstOrFail();

        if ($invoice->isPaid()) {
            return back()->with('error', 'Invoice sudah dibayar.');
        }

        $settings = $invoice->owner?->getSettings();
        if (!$settings || !$settings->enable_manual_payment) {
            return back()->with('error', 'Pembayaran manual tidak tersedia.');
        }

        $request->validate([
            'payment_proof'      => 'required|image|max:5120',
            'bank_account_id'    => 'required|exists:bank_accounts,id',
            'amount_transferred' => 'required|numeric|min:0',
            'transfer_date'      => 'required|date',
            'notes'              => 'nullable|string|max:500',
        ]);

        // Cek tidak ada pending payment yang sudah ada
        $existingPending = Payment::where('invoice_id', $invoice->id)->where('status', 'pending')->exists();
        if ($existingPending) {
            return back()->with('error', 'Bukti transfer sudah pernah dikirim dan masih menunggu konfirmasi admin.');
        }

        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        Payment::create([
            'payment_number'     => Payment::generatePaymentNumber(),
            'payment_type'       => 'invoice',
            'user_id'            => $invoice->owner_id,
            'invoice_id'         => $invoice->id,
            'payment_method'     => 'bank_transfer',
            'payment_channel'    => 'manual',
            'amount'             => $invoice->total,
            'fee'                => 0,
            'total_amount'       => $invoice->total,
            'status'             => 'pending',
            'merchant_ref'       => 'MANUAL-' . $invoice->id . '-' . time(),
            'payment_proof'      => $path,
            'bank_account_id'    => $request->bank_account_id,
            'amount_transferred' => $request->amount_transferred,
            'transfer_date'      => $request->transfer_date,
            'notes'              => $request->notes,
        ]);

        return redirect()->route('customer.invoice', $token)
            ->with('success', 'Bukti pembayaran berhasil dikirim. Admin akan mengkonfirmasi dalam 1x24 jam.');
    }

    public function customerStorePayment(string $token, Request $request)
    {
        $invoice = Invoice::where('payment_token', $token)->firstOrFail();

        if ($invoice->isPaid()) {
            return back()->with('error', 'Invoice sudah dibayar.');
        }

        $request->validate(['payment_channel' => 'required|string']);

        $settings = $invoice->owner?->getSettings();
        if (!$settings || !$settings->hasPaymentGateway()) {
            return back()->with('error', 'Pembayaran online tidak tersedia.');
        }

        $gateway = $this->resolveGateway($settings);
        $result  = $gateway->createInvoicePayment($invoice, $request->payment_channel, $settings->payment_expiry_hours);

        if ($result['success']) {
            $payment = $result['payment'];
            $data    = $result['data'];

            // Tag payment jika menggunakan platform gateway
            if ($settings->isUsingPlatformGateway()) {
                $payment->update([
                    'via_platform_gateway' => true,
                    'payment_gateway_id'   => $settings->platform_payment_gateway_id,
                ]);
            }

            return view('payments.detail', compact('invoice', 'payment', 'data'));
        }

        return back()->with('error', $result['message'] ?? 'Gagal membuat pembayaran.');
    }

    public function pendingIndex(Request $request)
    {
        $user = auth()->user();

        if ($user->isSubUser() && !in_array($user->role, ['keuangan', 'cs'])) {
            abort(403);
        }

        $payments = Payment::query()
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'pending')
            ->whereHas('invoice', fn($q) => $q->accessibleBy($user))
            ->with(['invoice'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                // Prioritas: kolom dedicated, fallback ke parsing notes (data lama)
                $proofPath = $p->payment_proof;
                $amountTransferred = $p->amount_transferred;
                $transferDate = $p->transfer_date;
                $catatan = $p->notes;

                if (!$proofPath && $p->notes) {
                    foreach (explode("\n", $p->notes) as $line) {
                        if (str_starts_with($line, 'Bukti transfer: ')) $proofPath = trim(substr($line, 16));
                        elseif (str_starts_with($line, 'Jumlah: ')) $amountTransferred = trim(substr($line, 8));
                        elseif (str_starts_with($line, 'Tanggal: ')) $transferDate = trim(substr($line, 9));
                        elseif (str_starts_with($line, 'Catatan: ')) $catatan = trim(substr($line, 9));
                    }
                }

                $invoice = $p->invoice;
                return (object) [
                    'id'                 => $p->id,
                    'payment_number'     => $p->payment_number,
                    'invoice_number'     => $invoice?->invoice_number ?? '-',
                    'invoice_url'        => $invoice ? route('invoices.show', $invoice->id) : null,
                    'customer_name'      => $invoice?->customer_name ?? '-',
                    'customer_id'        => $invoice?->customer_id ?? '-',
                    'amount'             => number_format((float) $p->amount, 0, ',', '.'),
                    'amount_transferred' => $amountTransferred ? number_format((float) $amountTransferred, 0, ',', '.') : '-',
                    'transfer_date'      => $transferDate ? (is_string($transferDate) ? $transferDate : $transferDate->format('Y-m-d')) : '-',
                    'proof_url'          => $proofPath ? asset('storage/' . $proofPath) : null,
                    'uploaded_at'        => $p->created_at->format('d/m/Y H:i'),
                    'confirm_url'        => route('payments.confirm-manual', $p->id),
                    'reject_url'         => route('payments.reject-manual', $p->id),
                    'catatan'            => $catatan,
                ];
            });

        return view('payments.pending', compact('payments'));
    }

    public function pendingDatatable(Request $request)
    {
        $user   = auth()->user();
        $search = $request->input('search.value', '');

        $query = Payment::query()
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'pending')
            ->whereHas('invoice', fn($q) => $q->accessibleBy($user))
            ->with(['invoice.pppUser'])
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('invoice', fn($qi) => $qi->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at');

        $total    = Payment::query()
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'pending')
            ->whereHas('invoice', fn($q) => $q->accessibleBy($user))
            ->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(function ($p) {
                // Prioritas: kolom dedicated, fallback ke parsing notes (data lama)
                $proofPath = $p->payment_proof;
                $amountTransferred = $p->amount_transferred;
                $transferDate = $p->transfer_date;
                $catatan = $p->notes;

                if (!$proofPath && $p->notes) {
                    foreach (explode("\n", $p->notes) as $line) {
                        if (str_starts_with($line, 'Bukti transfer: ')) $proofPath = trim(substr($line, strlen('Bukti transfer: ')));
                        elseif (str_starts_with($line, 'Jumlah: ')) $amountTransferred = trim(substr($line, strlen('Jumlah: ')));
                        elseif (str_starts_with($line, 'Tanggal: ')) $transferDate = trim(substr($line, strlen('Tanggal: ')));
                        elseif (str_starts_with($line, 'Catatan: ')) $catatan = trim(substr($line, strlen('Catatan: ')));
                    }
                }

                $invoice = $p->invoice;
                return [
                    'id'                => $p->id,
                    'payment_number'    => $p->payment_number,
                    'invoice_number'    => $invoice?->invoice_number ?? '-',
                    'customer_name'     => $invoice?->customer_name ?? '-',
                    'customer_id'       => $invoice?->customer_id ?? '-',
                    'amount'            => number_format($p->amount, 0, ',', '.'),
                    'amount_transferred'=> $amountTransferred ? number_format((float)$amountTransferred, 0, ',', '.') : '-',
                    'transfer_date'     => $transferDate ? (is_string($transferDate) ? $transferDate : $transferDate->format('Y-m-d')) : '-',
                    'proof_url'         => $proofPath ? asset('storage/' . $proofPath) : null,
                    'uploaded_at'       => $p->created_at->format('Y-m-d H:i'),
                    'confirm_url'       => route('payments.confirm-manual', $p->id),
                    'reject_url'        => route('payments.reject-manual', $p->id),
                    'invoice_url'       => $invoice ? route('invoices.show', $invoice->id) : null,
                    'catatan'           => $catatan,
                ];
            }),
        ]);
    }

    public function confirmManual(Request $request, Payment $payment)
    {
        $user = auth()->user();

        // Only owner or super admin can confirm
        if (!$user->isSuperAdmin()) {
            $invoice = $payment->invoice;
            if (!$invoice || $invoice->owner_id !== $user->effectiveOwnerId()) {
                abort(403);
            }
        }

        $payment->markAsPaid([
            'confirmed_by' => $user->id,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        if ($payment->invoice) {
            $invSettings = TenantSettings::getOrCreate((int) $payment->invoice->owner_id);
            WaNotificationService::notifyInvoicePaid($invSettings, $payment->invoice->fresh()->load('pppUser'));
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Pembayaran berhasil dikonfirmasi.']);
        }

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    public function rejectManual(Request $request, Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            $invoice = $payment->invoice;
            if (!$invoice || $invoice->owner_id !== $user->effectiveOwnerId()) {
                abort(403);
            }
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $payment->update([
            'status' => 'failed',
            'notes' => $payment->notes . "\n\nDitolak: " . $request->rejection_reason,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Pembayaran ditolak.']);
        }

        return back()->with('success', 'Pembayaran ditolak.');
    }
}
