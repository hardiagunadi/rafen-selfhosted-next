<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripayService implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected string $privateKey;
    protected string $merchantCode;
    protected string $baseUrl;
    protected bool $sandbox;

    public function __construct(?TenantSettings $settings = null)
    {
        if ($settings) {
            $this->apiKey = $settings->tripay_api_key ?? '';
            $this->privateKey = $settings->tripay_private_key ?? '';
            $this->merchantCode = $settings->tripay_merchant_code ?? '';
            $this->sandbox = $settings->tripay_sandbox ?? true;
        } else {
            $this->apiKey = config('tripay.api_key', '');
            $this->privateKey = config('tripay.private_key', '');
            $this->merchantCode = config('tripay.merchant_code', '');
            $this->sandbox = config('tripay.sandbox', true);
        }

        $this->baseUrl = $this->sandbox
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
    }

    public static function forTenant(TenantSettings $settings): self
    {
        return new self($settings);
    }

    public static function forSystem(): self
    {
        return new self(null);
    }

    public static function fromGateway(\App\Models\PaymentGateway $gateway): self
    {
        $instance = new self(null);
        $instance->apiKey       = $gateway->api_key ?? '';
        $instance->privateKey   = $gateway->private_key ?? '';
        $instance->merchantCode = $gateway->merchant_code ?? '';
        $instance->sandbox      = $gateway->is_sandbox;
        $instance->baseUrl      = $gateway->is_sandbox
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
        return $instance;
    }

    public function getPaymentChannels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/merchant/payment-channel');

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::error('Tripay get channels failed', ['response' => $response->json()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Tripay get channels exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function calculateFee(string $channelCode, int $amount): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/merchant/fee-calculator', [
                'code' => $channelCode,
                'amount' => $amount,
            ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Tripay fee calculation error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function createTransaction(array $params): array
    {
        $merchantRef = $params['merchant_ref'] ?? Payment::generatePaymentNumber();
        $amount = (int) $params['amount'];
        $channelCode = $params['channel_code'];
        $customerName = $params['customer_name'];
        $customerEmail = $params['customer_email'];
        $customerPhone = $params['customer_phone'] ?? '';
        $orderItems = $params['order_items'] ?? [];
        $expiredTime = $params['expired_time'] ?? (time() + (24 * 60 * 60));
        $callbackUrl = $params['callback_url'] ?? route('payment.callback');
        $returnUrl = $params['return_url'] ?? route('payment.success');

        $signature = hash_hmac('sha256', $this->merchantCode . $merchantRef . $amount, $this->privateKey);

        $payload = [
            'method' => $channelCode,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'order_items' => $orderItems,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'expired_time' => $expiredTime,
            'signature' => $signature,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/transaction/create', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            Log::error('Tripay create transaction failed', [
                'payload' => $payload,
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message', 'Gagal membuat transaksi'),
            ];
        } catch (\Exception $e) {
            Log::error('Tripay create transaction exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getTransactionDetail(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/transaction/detail', [
                'reference' => $reference,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message', 'Transaksi tidak ditemukan'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function verifyCallback(array $data): bool
    {
        $callbackSignature = $data['signature'] ?? '';
        $merchantRef = $data['merchant_ref'] ?? '';
        $status = $data['status'] ?? '';

        $localSignature = hash_hmac('sha256', $this->merchantCode . $merchantRef . $status, $this->privateKey);

        return hash_equals($localSignature, $callbackSignature);
    }

    public function createInvoicePayment(Invoice $invoice, string $channelCode, int $expiryHours = 24): array
    {
        $owner = $invoice->owner;
        $pppUser = $invoice->pppUser;

        $orderItems = [
            [
                'sku' => $invoice->invoice_number,
                'name' => $invoice->paket_langganan ?? 'Layanan Internet',
                'price' => (int) $invoice->total,
                'quantity' => 1,
            ],
        ];

        $result = $this->createTransaction([
            'merchant_ref' => 'INV-' . $invoice->id . '-' . time(),
            'amount' => (int) $invoice->total,
            'channel_code' => $channelCode,
            'customer_name' => $pppUser->customer_name ?? $invoice->customer_name ?? 'Customer',
            'customer_email' => $pppUser->email ?? $owner->email ?? 'customer@example.com',
            'customer_phone' => $pppUser->nomor_hp ?? '',
            'order_items' => $orderItems,
            'expired_time' => time() + ($expiryHours * 3600),
            'callback_url' => route('payment.callback'),
            'return_url' => route('invoices.show', $invoice),
        ]);

        if ($result['success']) {
            $data = $result['data'];

            $payment = Payment::create([
                'payment_number' => Payment::generatePaymentNumber(),
                'payment_type' => 'invoice',
                'user_id' => $owner->id,
                'invoice_id' => $invoice->id,
                'payment_channel' => $channelCode,
                'payment_method' => $this->getPaymentMethod($channelCode),
                'amount' => $invoice->total,
                'fee' => $data['total_fee'] ?? 0,
                'total_amount' => $data['amount'] ?? $invoice->total,
                'status' => 'pending',
                'reference' => $data['reference'],
                'merchant_ref' => $data['merchant_ref'],
                'checkout_url' => $data['checkout_url'] ?? null,
                'qr_url' => $data['qr_url'] ?? null,
                'qr_string' => $data['qr_string'] ?? null,
                'pay_code' => $data['pay_code'] ?? null,
                'payment_instructions' => $data['instructions'] ?? null,
                'expired_at' => isset($data['expired_time']) ? \Carbon\Carbon::createFromTimestamp($data['expired_time']) : null,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'data' => $data,
            ];
        }

        return $result;
    }

    public function createSubscriptionPayment(Subscription $subscription, string $channelCode, int $expiryHours = 24): array
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $orderItems = [
            [
                'sku' => 'SUB-' . $plan->slug,
                'name' => 'Langganan ' . $plan->name,
                'price' => (int) $subscription->amount_paid,
                'quantity' => 1,
            ],
        ];

        $result = $this->createTransaction([
            'merchant_ref' => 'SUB-' . $subscription->id . '-' . time(),
            'amount' => (int) $subscription->amount_paid,
            'channel_code' => $channelCode,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? '',
            'order_items' => $orderItems,
            'expired_time' => time() + ($expiryHours * 3600),
            'callback_url' => route('subscription.payment.callback'),
            'return_url' => route('subscription.index'),
        ]);

        if ($result['success']) {
            $data = $result['data'];

            $payment = Payment::create([
                'payment_number' => Payment::generatePaymentNumber(),
                'payment_type' => 'subscription',
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'payment_channel' => $channelCode,
                'payment_method' => $this->getPaymentMethod($channelCode),
                'amount' => $subscription->amount_paid,
                'fee' => $data['total_fee'] ?? 0,
                'total_amount' => $data['amount'] ?? $subscription->amount_paid,
                'status' => 'pending',
                'reference' => $data['reference'],
                'merchant_ref' => $data['merchant_ref'],
                'checkout_url' => $data['checkout_url'] ?? null,
                'qr_url' => $data['qr_url'] ?? null,
                'qr_string' => $data['qr_string'] ?? null,
                'pay_code' => $data['pay_code'] ?? null,
                'payment_instructions' => $data['instructions'] ?? null,
                'expired_at' => isset($data['expired_time']) ? \Carbon\Carbon::createFromTimestamp($data['expired_time']) : null,
            ]);

            $subscription->update(['payment_reference' => $data['reference']]);

            return [
                'success' => true,
                'payment' => $payment,
                'data' => $data,
            ];
        }

        return $result;
    }

    protected function getPaymentMethod(string $channelCode): string
    {
        $qrisCodes = ['QRIS', 'QRISC', 'QRISOP', 'QRISD'];
        $vaCodes = ['BRIVA', 'BCAVA', 'BNIVA', 'MANDIRIVA', 'CIMBVA', 'BSIVA', 'OCBCVA', 'DANAMONVA', 'PERMATA'];

        if (in_array(strtoupper($channelCode), $qrisCodes)) {
            return 'qris';
        }

        if (in_array(strtoupper($channelCode), $vaCodes)) {
            return 'virtual_account';
        }

        return 'other';
    }

    public static function getChannelGroups(): array
    {
        return [
            'qris' => [
                'name' => 'QRIS',
                'description' => 'Bayar dengan scan QR',
                'codes' => ['QRIS', 'QRISC', 'QRISOP', 'QRISD'],
            ],
            'virtual_account' => [
                'name' => 'Virtual Account',
                'description' => 'Transfer ke Virtual Account',
                'codes' => ['BRIVA', 'BCAVA', 'BNIVA', 'MANDIRIVA', 'CIMBVA', 'BSIVA', 'OCBCVA', 'DANAMONVA', 'PERMATA'],
            ],
            'ewallet' => [
                'name' => 'E-Wallet',
                'description' => 'Bayar dengan E-Wallet',
                'codes' => ['OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA', 'GOPAY'],
            ],
            'convenience_store' => [
                'name' => 'Convenience Store',
                'description' => 'Bayar di minimarket',
                'codes' => ['ALFAMART', 'INDOMARET'],
            ],
        ];
    }
}
