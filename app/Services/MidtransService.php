<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService implements PaymentGatewayInterface
{
    protected string $serverKey;
    protected string $clientKey;
    protected string $baseUrl;
    protected bool $sandbox;

    public function __construct(?TenantSettings $settings = null)
    {
        if ($settings) {
            $this->serverKey = $settings->midtrans_server_key ?? '';
            $this->clientKey = $settings->midtrans_client_key ?? '';
            $this->sandbox   = $settings->midtrans_sandbox ?? true;
        } else {
            $this->serverKey = config('services.midtrans.server_key', '');
            $this->clientKey = config('services.midtrans.client_key', '');
            $this->sandbox   = config('services.midtrans.sandbox', true);
        }

        $this->baseUrl = $this->sandbox
            ? 'https://api.sandbox.midtrans.com'
            : 'https://api.midtrans.com';
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
        $instance->serverKey = $gateway->api_key ?? '';
        $instance->clientKey = $gateway->merchant_code ?? '';
        $instance->sandbox   = $gateway->is_sandbox;
        $instance->baseUrl   = $gateway->is_sandbox
            ? 'https://api.sandbox.midtrans.com'
            : 'https://api.midtrans.com';
        return $instance;
    }

    protected function authHeader(): string
    {
        return 'Basic ' . base64_encode($this->serverKey . ':');
    }

    public function getPaymentChannels(): array
    {
        // Midtrans channels are static; return common ones
        return [
            ['code' => 'QRIS',    'name' => 'QRIS',              'group' => 'qris'],
            ['code' => 'BRIVA',   'name' => 'BRI Virtual Account','group' => 'virtual_account'],
            ['code' => 'BCAVA',   'name' => 'BCA Virtual Account','group' => 'virtual_account'],
            ['code' => 'BNIVA',   'name' => 'BNI Virtual Account','group' => 'virtual_account'],
            ['code' => 'MANDIRI', 'name' => 'Mandiri Bill',       'group' => 'virtual_account'],
            ['code' => 'PERMATA', 'name' => 'Permata Virtual Account','group' => 'virtual_account'],
        ];
    }

    public function createTransaction(array $params): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authHeader(),
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl . '/v2/charge', $params);

            $data = $response->json();

            if ($response->successful() && in_array($data['status_code'] ?? '', ['200', '201'])) {
                return ['success' => true, 'data' => $data];
            }

            Log::error('Midtrans createTransaction failed', ['response' => $data]);
            return ['success' => false, 'message' => $data['status_message'] ?? 'Transaksi gagal', 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('Midtrans createTransaction exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createInvoicePayment(Invoice $invoice, string $channel, int $expiryHours = 24): array
    {
        $orderId     = 'INV-' . $invoice->id . '-' . time();
        $amount      = (int) $invoice->total;
        $expiry      = now()->addHours($expiryHours);

        $payload = [
            'payment_type' => $this->mapChannelToPaymentType($channel),
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $invoice->customer_name ?? 'Pelanggan',
                'email'      => $invoice->pppUser?->email ?? '',
                'phone'      => $invoice->pppUser?->no_hp ?? '',
            ],
            'item_details' => [[
                'id'       => 'PKT-' . $invoice->id,
                'price'    => $amount,
                'quantity' => 1,
                'name'     => 'Tagihan ' . ($invoice->paket_langganan ?? 'Internet'),
            ]],
            'custom_expiry' => [
                'order_time'      => now()->format('Y-m-d H:i:s O'),
                'expiry_duration' => $expiryHours * 60,
                'unit'            => 'minute',
            ],
        ];

        // Map channel ke Midtrans bank_transfer / e-wallet structure
        $payload = array_merge($payload, $this->buildChannelPayload($channel));

        $result = $this->createTransaction($payload);

        if (! $result['success']) {
            return $result;
        }

        $data = $result['data'];

        $payment = Payment::create([
            'payment_number'    => Payment::generatePaymentNumber(),
            'payment_type'      => 'invoice',
            'user_id'           => $invoice->owner_id,
            'invoice_id'        => $invoice->id,
            'payment_channel'   => $channel,
            'payment_method'    => $this->getPaymentMethod($channel),
            'amount'            => $amount,
            'fee'               => 0,
            'total_amount'      => $amount,
            'status'            => 'pending',
            'reference'         => $data['transaction_id'] ?? null,
            'merchant_ref'      => $orderId,
            'qr_url'            => $data['qr_image_url'] ?? ($data['actions'][0]['url'] ?? null),
            'qr_string'         => $data['qr_string'] ?? null,
            'pay_code'          => $data['va_numbers'][0]['va_number'] ?? ($data['bill_key'] ?? null),
            'payment_instructions' => json_encode($data),
            'expired_at'        => $expiry,
        ]);

        return ['success' => true, 'payment' => $payment, 'data' => $data];
    }

    public function createSubscriptionPayment(Subscription $subscription, string $channel, int $expiryHours = 24): array
    {
        $orderId = 'SUB-' . $subscription->id . '-' . time();
        $amount  = (int) $subscription->amount;
        $expiry  = now()->addHours($expiryHours);

        $payload = [
            'payment_type' => $this->mapChannelToPaymentType($channel),
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $subscription->user?->name ?? 'Tenant',
                'email'      => $subscription->user?->email ?? '',
            ],
            'item_details' => [[
                'id'       => 'SUB-' . $subscription->id,
                'price'    => $amount,
                'quantity' => 1,
                'name'     => 'Langganan Aplikasi',
            ]],
        ];

        $payload = array_merge($payload, $this->buildChannelPayload($channel));

        $result = $this->createTransaction($payload);
        if (! $result['success']) {
            return $result;
        }

        $data = $result['data'];

        $payment = Payment::create([
            'payment_number'    => Payment::generatePaymentNumber(),
            'payment_type'      => 'subscription',
            'user_id'           => $subscription->user_id,
            'subscription_id'   => $subscription->id,
            'payment_channel'   => $channel,
            'payment_method'    => $this->getPaymentMethod($channel),
            'amount'            => $amount,
            'fee'               => 0,
            'total_amount'      => $amount,
            'status'            => 'pending',
            'reference'         => $data['transaction_id'] ?? null,
            'merchant_ref'      => $orderId,
            'pay_code'          => $data['va_numbers'][0]['va_number'] ?? ($data['bill_key'] ?? null),
            'payment_instructions' => json_encode($data),
            'expired_at'        => $expiry,
        ]);

        $subscription->update(['payment_reference' => $orderId]);

        return ['success' => true, 'payment' => $payment, 'data' => $data];
    }

    public function getTransactionDetail(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authHeader(),
            ])->get($this->baseUrl . '/v2/' . $reference . '/status');

            $data = $response->json();
            return ['success' => $response->successful(), 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('Midtrans getTransactionDetail exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyCallback(array $data): bool
    {
        // Midtrans signature: SHA512(order_id + status_code + gross_amount + server_key)
        $orderId     = $data['order_id'] ?? '';
        $statusCode  = $data['status_code'] ?? '';
        $grossAmount = $data['gross_amount'] ?? '';

        $expectedSig = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($expectedSig, $data['signature_key'] ?? '');
    }

    protected function mapChannelToPaymentType(string $channel): string
    {
        return match (true) {
            str_starts_with($channel, 'QRIS') => 'qris',
            in_array($channel, ['BRIVA', 'BCAVA', 'BNIVA', 'MANDIRI', 'PERMATA']) => 'bank_transfer',
            default => 'bank_transfer',
        };
    }

    protected function buildChannelPayload(string $channel): array
    {
        return match ($channel) {
            'BRIVA'   => ['bank_transfer' => ['bank' => 'bri']],
            'BCAVA'   => ['bank_transfer' => ['bank' => 'bca']],
            'BNIVA'   => ['bank_transfer' => ['bank' => 'bni']],
            'MANDIRI' => ['bank_transfer' => ['bank' => 'mandiri']],
            'PERMATA' => ['bank_transfer' => ['bank' => 'permata']],
            default   => [],
        };
    }

    protected function getPaymentMethod(string $channel): string
    {
        if (str_starts_with($channel, 'QRIS')) return 'qris';
        return 'virtual_account';
    }
}
