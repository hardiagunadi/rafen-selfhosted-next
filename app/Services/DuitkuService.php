<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DuitkuService implements PaymentGatewayInterface
{
    protected string $merchantCode;
    protected string $apiKey;
    protected string $baseUrl;
    protected bool $sandbox;

    // Duitku channel codes (sandbox: VA only; production: tambah BT/QRIS, OV, DA setelah diaktifkan)
    const CHANNELS_SANDBOX = [
        ['code' => 'BR', 'name' => 'BRI Virtual Account',     'group' => 'virtual_account'],
        ['code' => 'BC', 'name' => 'BCA Virtual Account',     'group' => 'virtual_account'],
        ['code' => 'B1', 'name' => 'BNI Virtual Account',     'group' => 'virtual_account'],
        ['code' => 'M2', 'name' => 'Mandiri Virtual Account', 'group' => 'virtual_account'],
    ];

    const CHANNELS_PRODUCTION = [
        ['code' => 'BT', 'name' => 'QRIS',                    'group' => 'qris'],
        ['code' => 'BR', 'name' => 'BRI Virtual Account',     'group' => 'virtual_account'],
        ['code' => 'BC', 'name' => 'BCA Virtual Account',     'group' => 'virtual_account'],
        ['code' => 'B1', 'name' => 'BNI Virtual Account',     'group' => 'virtual_account'],
        ['code' => 'M2', 'name' => 'Mandiri Virtual Account', 'group' => 'virtual_account'],
        ['code' => 'OV', 'name' => 'OVO',                     'group' => 'ewallet'],
        ['code' => 'DA', 'name' => 'DANA',                    'group' => 'ewallet'],
    ];

    public function __construct(?TenantSettings $settings = null)
    {
        if ($settings) {
            $this->merchantCode = $settings->duitku_merchant_code ?? '';
            $this->apiKey       = $settings->duitku_api_key ?? '';
            $this->sandbox      = $settings->duitku_sandbox ?? true;
        } else {
            $this->merchantCode = config('services.duitku.merchant_code', '');
            $this->apiKey       = config('services.duitku.api_key', '');
            $this->sandbox      = config('services.duitku.sandbox', true);
        }

        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.duitku.com/webapi/api'
            : 'https://passport.duitku.com/webapi/api';
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
        $instance->merchantCode = $gateway->merchant_code ?? '';
        $instance->apiKey       = $gateway->api_key ?? '';
        $instance->sandbox      = $gateway->is_sandbox;
        $instance->baseUrl      = $gateway->is_sandbox
            ? 'https://sandbox.duitku.com/webapi/api'
            : 'https://passport.duitku.com/webapi/api';
        return $instance;
    }

    protected function signature(string $merchantCode, string $merchantOrderId, int $amount): string
    {
        return md5($merchantCode . $merchantOrderId . $amount . $this->apiKey);
    }

    protected function callbackSignature(string $merchantCode, string $amount, string $merchantOrderId): string
    {
        return md5($merchantCode . $amount . $merchantOrderId . $this->apiKey);
    }

    public function getPaymentChannels(): array
    {
        try {
            $amount    = 10000;
            $datetime  = now()->format('Y-m-d H:i:s');
            $signature = hash('sha256', $this->merchantCode . $amount . $datetime . $this->apiKey);

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl . '/merchant/paymentmethod/getpaymentmethod', [
                    'merchantcode' => $this->merchantCode,
                    'amount'       => $amount,
                    'datetime'     => $datetime,
                    'signature'    => $signature,
                ]);

            if ($response->successful() && $response->json('responseCode') === '00') {
                return array_map(fn ($fee) => [
                    'code'     => $fee['paymentMethod'],
                    'name'     => $fee['paymentName'],
                    'icon_url' => $fee['paymentImage'] ?? null,
                    'fee'      => $fee['totalFee'] ?? 0,
                    'group'    => $this->getPaymentMethod($fee['paymentMethod']),
                ], $response->json('paymentFee', []));
            }

            Log::warning('Duitku getPaymentChannels failed', ['response' => $response->json()]);
        } catch (\Throwable $e) {
            Log::warning('Duitku getPaymentChannels exception', ['error' => $e->getMessage()]);
        }

        return $this->sandbox ? self::CHANNELS_SANDBOX : self::CHANNELS_PRODUCTION;
    }

    public function createTransaction(array $params): array
    {
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl . '/merchant/v2/inquiry', $params);
            $data     = $response->json();

            if ($response->successful() && isset($data['reference'])) {
                return ['success' => true, 'data' => $data];
            }

            Log::error('Duitku createTransaction failed', ['response' => $data]);
            return ['success' => false, 'message' => $data['Message'] ?? $data['message'] ?? 'Transaksi gagal', 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('Duitku createTransaction exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createInvoicePayment(Invoice $invoice, string $channel, int $expiryHours = 24): array
    {
        $merchantOrderId = 'INV-' . $invoice->id . '-' . time();
        $amount          = (int) $invoice->total;
        $expiry          = now()->addHours($expiryHours);

        $params = [
            'merchantCode'   => $this->merchantCode,
            'paymentAmount'  => $amount,
            'paymentMethod'  => $channel,
            'merchantOrderId'=> $merchantOrderId,
            'productDetails' => 'Tagihan ' . ($invoice->paket_langganan ?? 'Internet'),
            'customerVaName' => $invoice->customer_name ?? 'Pelanggan',
            'email'          => $invoice->pppUser?->email ?? '',
            'phoneNumber'    => $invoice->pppUser?->no_hp ?? '',
            'itemDetails'    => [[
                'name'     => 'Tagihan ' . ($invoice->paket_langganan ?? 'Internet'),
                'price'    => $amount,
                'quantity' => 1,
            ]],
            'callbackUrl'    => url('/payment/callback/duitku'),
            'returnUrl'      => url('/payment/success'),
            'expiryPeriod'   => $expiryHours * 60,
            'signature'      => $this->signature($this->merchantCode, $merchantOrderId, $amount),
        ];

        $result = $this->createTransaction($params);
        if (! $result['success']) {
            return $result;
        }

        $data = $result['data'];

        $payment = Payment::create([
            'payment_number'       => Payment::generatePaymentNumber(),
            'payment_type'         => 'invoice',
            'user_id'              => $invoice->owner_id,
            'invoice_id'           => $invoice->id,
            'payment_channel'      => $channel,
            'payment_method'       => $this->getPaymentMethod($channel),
            'amount'               => $amount,
            'fee'                  => 0,
            'total_amount'         => $amount,
            'status'               => 'pending',
            'reference'            => $data['reference'] ?? null,
            'merchant_ref'         => $merchantOrderId,
            'checkout_url'         => $data['paymentUrl'] ?? null,
            'qr_url'               => !empty($data['qrString']) ? 'qr' : null,
            'qr_string'            => $data['qrString'] ?? null,
            'pay_code'             => $data['vaNumber'] ?? null,
            'payment_instructions' => json_encode($data),
            'expired_at'           => $expiry,
        ]);

        return ['success' => true, 'payment' => $payment, 'data' => $data];
    }

    public function createSubscriptionPayment(Subscription $subscription, string $channel, int $expiryHours = 24): array
    {
        $merchantOrderId = 'SUB-' . $subscription->id . '-' . time();
        $amount          = (int) $subscription->amount_paid;
        $expiry          = now()->addHours($expiryHours);

        $params = [
            'merchantCode'    => $this->merchantCode,
            'paymentAmount'   => $amount,
            'paymentMethod'   => $channel,
            'merchantOrderId' => $merchantOrderId,
            'productDetails'  => 'Langganan Aplikasi',
            'customerVaName'  => $subscription->user?->name ?? 'Tenant',
            'email'           => $subscription->user?->email ?? '',
            'callbackUrl'     => url('/subscription/payment/callback'),
            'returnUrl'       => url('/subscription'),
            'expiryPeriod'    => $expiryHours * 60,
            'signature'       => $this->signature($this->merchantCode, $merchantOrderId, $amount),
        ];

        $result = $this->createTransaction($params);
        if (! $result['success']) {
            return $result;
        }

        $data = $result['data'];

        $payment = Payment::create([
            'payment_number'       => Payment::generatePaymentNumber(),
            'payment_type'         => 'subscription',
            'user_id'              => $subscription->user_id,
            'subscription_id'      => $subscription->id,
            'payment_channel'      => $channel,
            'payment_method'       => $this->getPaymentMethod($channel),
            'amount'               => $amount,
            'fee'                  => 0,
            'total_amount'         => $amount,
            'status'               => 'pending',
            'reference'            => $data['reference'] ?? null,
            'merchant_ref'         => $merchantOrderId,
            'checkout_url'         => $data['paymentUrl'] ?? null,
            'pay_code'             => $data['vaNumber'] ?? null,
            'payment_instructions' => json_encode($data),
            'expired_at'           => $expiry,
        ]);

        $subscription->update(['payment_reference' => $merchantOrderId]);

        return ['success' => true, 'payment' => $payment, 'data' => $data];
    }

    public function getTransactionDetail(string $reference): array
    {
        try {
            $params = [
                'merchantCode'    => $this->merchantCode,
                'merchantOrderId' => $reference,
                'signature'       => md5($this->merchantCode . $reference . $this->apiKey),
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl . '/merchant/transactionList', $params);
            $data     = $response->json();
            return ['success' => $response->successful(), 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('Duitku getTransactionDetail exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyCallback(array $data): bool
    {
        // Duitku: MD5(merchantCode + amount + merchantOrderId + apiKey)
        $merchantCode    = $data['merchantCode'] ?? '';
        $amount          = $data['amount'] ?? '';
        $merchantOrderId = $data['merchantOrderId'] ?? '';

        $expected = md5($merchantCode . $amount . $merchantOrderId . $this->apiKey);
        return hash_equals($expected, $data['signature'] ?? '');
    }

    protected function getPaymentMethod(string $channel): string
    {
        if ($channel === 'BT') return 'qris';
        if (in_array($channel, ['OV', 'DA'])) return 'ewallet';
        return 'virtual_account';
    }
}
