<?php

namespace App\Http\Controllers;

use App\Mail\TenantInvoiceCreated;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\PaymentGateway;
use App\Services\DuitkuService;
use App\Services\MidtransService;
use App\Services\TripayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $currentSubscription = $user->activeSubscription;
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('subscription.index', compact('currentSubscription', 'plans', 'user'));
    }

    public function subscriptionsDatatable(Request $request)
    {
        $user = $request->user();
        $search = $request->input('search.value', '');

        $query = $user->subscriptions()
            ->with('plan')
            ->when($search !== '', fn ($q) => $q->whereHas('plan', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->orderByDesc('created_at');

        $total = $user->subscriptions()->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 10)))
            ->get();

        $statusLabels = [
            'active' => '<span class="badge badge-success">Aktif</span>',
            'pending' => '<span class="badge badge-warning">Menunggu Pembayaran</span>',
            'expired' => '<span class="badge badge-secondary">Berakhir</span>',
            'cancelled' => '<span class="badge badge-danger">Dibatalkan</span>',
        ];

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'plan' => $r->plan->name ?? '-',
                'start_date' => $r->start_date->format('d M Y'),
                'end_date' => $r->end_date->format('d M Y'),
                'status' => $statusLabels[$r->status] ?? $r->status,
                'amount' => 'Rp '.number_format($r->amount_paid, 0, ',', '.'),
            ]),
        ]);
    }

    public function plans()
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();
        $user = auth()->user();

        return view('subscription.plans', compact('plans', 'user'));
    }

    public function publicPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get()
            ->map(fn ($plan) => [
                'name'                => $plan->name,
                'slug'                => $plan->slug,
                'description'         => $plan->description,
                'price'               => $plan->price,
                'formatted_price'     => $plan->formatted_price,
                'duration_days'       => $plan->duration_days,
                'max_mikrotik'        => $plan->max_mikrotik,
                'max_ppp_users'       => $plan->max_ppp_users,
                'max_vpn_peers'       => $plan->max_vpn_peers,
                'features'            => $plan->features,
                'is_featured'         => $plan->is_featured,
                'unlimited_mikrotik'  => $plan->isUnlimitedMikrotik(),
                'unlimited_ppp_users' => $plan->isUnlimitedPppUsers(),
                'unlimited_vpn_peers' => $plan->isUnlimitedVpnPeers(),
            ]);

        return response()->json($plans)
            ->header('Access-Control-Allow-Origin', 'https://rafen.online')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function subscribe(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();
        $durationDays = $user->resolveSubscriptionDurationDays($plan);

        // Create pending subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($durationDays),
            'status' => 'pending',
            'amount_paid' => $plan->price,
            'payment_token' => Subscription::generatePaymentToken(),
        ]);

        // Send invoice email to tenant
        try {
            Mail::to($user->email)->queue(new TenantInvoiceCreated($user, $subscription));
        } catch (\Throwable $e) {
            Log::warning('Failed to send subscription invoice email', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('subscription.payment.public', $subscription->payment_token);
    }

    public function payment(Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        $token = $subscription->getOrCreatePaymentToken();

        return redirect()->route('subscription.payment.public', $token);
    }

    public function publicPayment(string $token)
    {
        $subscription = Subscription::where('payment_token', $token)
            ->with(['plan', 'user'])
            ->firstOrFail();

        if ($subscription->status !== 'pending') {
            return view('subscription.payment-done', compact('subscription'));
        }

        $gateways = PaymentGateway::active()->get();
        $channels = [];

        foreach ($gateways as $gw) {
            $service = match ($gw->provider) {
                'duitku'   => DuitkuService::fromGateway($gw),
                'tripay'   => TripayService::fromGateway($gw),
                default    => null,
            };
            if (! $service) continue;

            foreach ($service->getPaymentChannels() as $ch) {
                $ch['gateway_code'] = $gw->code;
                $ch['provider']     = $gw->provider;
                $channels[]         = $ch;
            }
        }

        return view('subscription.payment-public', compact('subscription', 'channels', 'token'));
    }

    public function processPayment(Request $request, Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        $token = $subscription->getOrCreatePaymentToken();

        return redirect()->route('subscription.payment.public', $token);
    }

    public function publicProcessPayment(Request $request, string $token)
    {
        $subscription = Subscription::where('payment_token', $token)->firstOrFail();

        if ($subscription->status !== 'pending') {
            return redirect()->route('subscription.payment.public', $token)
                ->with('error', 'Langganan ini sudah diproses.');
        }

        $request->validate([
            'payment_channel' => 'required|string',
            'gateway_code'    => 'required|string',
        ]);

        $gateway = PaymentGateway::active()->where('code', $request->gateway_code)->firstOrFail();

        $service = match ($gateway->provider) {
            'duitku' => DuitkuService::fromGateway($gateway),
            'tripay' => TripayService::fromGateway($gateway),
            default  => null,
        };

        if (! $service) {
            return back()->with('error', 'Provider payment tidak didukung.');
        }

        $result = $service->createSubscriptionPayment(
            $subscription,
            $request->payment_channel
        );

        if ($result['success']) {
            $payment = $result['payment'];
            $data    = $result['data'];

            return view('subscription.payment-detail-public', compact('subscription', 'payment', 'data', 'token'));
        }

        return back()->with('error', $result['message'] ?? 'Gagal membuat pembayaran.');
    }

    public function publicCheckStatus(string $token, int $paymentId)
    {
        $subscription = Subscription::where('payment_token', $token)->firstOrFail();

        $payment = \App\Models\Payment::where('id', $paymentId)
            ->where('subscription_id', $subscription->id)
            ->firstOrFail();

        if ($payment->status !== 'paid' && $payment->reference) {
            $gateway = $payment->payment_gateway_id
                ? \App\Models\PaymentGateway::find($payment->payment_gateway_id)
                : null;

            if ($gateway) {
                $service = match ($gateway->provider) {
                    'duitku' => DuitkuService::fromGateway($gateway),
                    'tripay' => TripayService::fromGateway($gateway),
                    default  => null,
                };

                if ($service) {
                    $result = $service->getTransactionDetail($payment->reference);
                    if ($result['success']) {
                        $gatewayStatus = $result['data']['status'] ?? '';
                        if ($gatewayStatus === 'PAID') {
                            $payment->markAsPaid($result['data']);
                        } elseif ($gatewayStatus === 'EXPIRED') {
                            $payment->markAsExpired();
                        }
                    }
                }
            }
        }

        return response()->json(['status' => $payment->fresh()->status]);
    }

    public function paymentCallback(Request $request)
    {
        $callbackData = $request->all();

        // Detect provider: Duitku uses merchantOrderId, Tripay uses merchant_ref
        $isDuitku = isset($callbackData['merchantOrderId']) && isset($callbackData['merchantCode']);

        if ($isDuitku) {
            $merchantRef = $callbackData['merchantOrderId'] ?? '';
            $gatewayModel = PaymentGateway::active()->where('provider', 'duitku')->first();
            if (! $gatewayModel) {
                return response()->json(['success' => false, 'message' => 'Gateway not found'], 404);
            }
            $service = DuitkuService::fromGateway($gatewayModel);
            if (! $service->verifyCallback($callbackData)) {
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
            }
            // Duitku resultCode: '00' = success
            $resultCode = $callbackData['resultCode'] ?? '';
            $status = $resultCode === '00' ? 'PAID' : 'FAILED';
        } else {
            $merchantRef = $callbackData['merchant_ref'] ?? '';
            $gatewayModel = PaymentGateway::active()->where('provider', 'tripay')->first();
            $service = $gatewayModel ? TripayService::fromGateway($gatewayModel) : TripayService::forSystem();
            if (! $service->verifyCallback($callbackData)) {
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
            }
            $status = $callbackData['status'] ?? '';
        }

        // Find payment by merchant reference
        $payment = \App\Models\Payment::where('merchant_ref', $merchantRef)->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        if ($status === 'PAID') {
            $payment->markAsPaid($callbackData);
        } elseif ($status === 'EXPIRED') {
            $payment->markAsExpired();
        } elseif ($status === 'FAILED') {
            $payment->markAsFailed();
        }

        return response()->json(['success' => true]);
    }

    public function expired()
    {
        $user = auth()->user();
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('subscription.expired', compact('user', 'plans'));
    }

    public function renew(Request $request)
    {
        $user = $request->user();
        $plan = $user->subscriptionPlan ?? SubscriptionPlan::active()->first();

        if (! $plan) {
            return redirect()->route('subscription.plans')
                ->with('error', 'Silakan pilih paket langganan.');
        }

        $durationDays = $user->resolveSubscriptionDurationDays($plan);
        $startDate = $user->subscription_expires_at ?? now();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays($durationDays),
            'status' => 'pending',
            'amount_paid' => $plan->price,
            'payment_token' => Subscription::generatePaymentToken(),
        ]);

        // Send invoice email to tenant
        try {
            Mail::to($user->email)->queue(new TenantInvoiceCreated($user, $subscription));
        } catch (\Throwable $e) {
            Log::warning('Failed to send renewal invoice email', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('subscription.payment.public', $subscription->payment_token);
    }

    public function history(Request $request)
    {
        return view('subscription.history');
    }

    public function historyDatatable(Request $request)
    {
        $user = $request->user();
        $search = $request->input('search.value', '');

        $query = $user->payments()
            ->where('payment_type', 'subscription')
            ->with('subscription.plan')
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('payment_channel', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total = $user->payments()->where('payment_type', 'subscription')->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        $statusLabels = [
            'paid' => '<span class="badge badge-success">Dibayar</span>',
            'pending' => '<span class="badge badge-warning">Menunggu</span>',
            'expired' => '<span class="badge badge-secondary">Kedaluwarsa</span>',
            'failed' => '<span class="badge badge-danger">Gagal</span>',
        ];

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'payment_number' => $r->payment_number,
                'plan' => $r->subscription?->plan?->name ?? '-',
                'payment_channel' => $r->payment_channel ?? '-',
                'total_amount' => 'Rp '.number_format($r->total_amount, 0, ',', '.'),
                'status' => $statusLabels[$r->status] ?? $r->status,
                'created_at' => $r->created_at->format('d M Y H:i'),
            ]),
        ]);
    }
}
