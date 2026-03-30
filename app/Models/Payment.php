<?php

namespace App\Models;

use App\Mail\TenantInvoiceCreated;
use App\Mail\TenantPaymentConfirmed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'payment_type',
        'user_id',
        'subscription_id',
        'invoice_id',
        'payment_gateway_id',
        'payment_channel',
        'payment_method',
        'amount',
        'fee',
        'total_amount',
        'status',
        'reference',
        'merchant_ref',
        'checkout_url',
        'qr_url',
        'qr_string',
        'pay_code',
        'payment_instructions',
        'expired_at',
        'paid_at',
        'callback_data',
        'notes',
        'payment_proof',
        'bank_account_id',
        'amount_transferred',
        'transfer_date',
        'rejection_reason',
        'via_platform_gateway',
        'platform_fee_amount',
        'tenant_net_amount',
        'wallet_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_instructions' => 'array',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'callback_data' => 'array',
            'via_platform_gateway' => 'boolean',
            'platform_fee_amount' => 'decimal:2',
            'tenant_net_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->expired_at && $this->expired_at->isPast());
    }

    public function markAsPaid(array $callbackData = []): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'callback_data' => $callbackData,
        ]);

        if ($this->payment_type === 'subscription' && $this->subscription) {
            $this->subscription->activate();

            // Send payment confirmation email to tenant
            try {
                $tenant = $this->user;
                if ($tenant && $tenant->email) {
                    Mail::to($tenant->email)->queue(new TenantPaymentConfirmed($tenant, $this->subscription->fresh(), $this->fresh()));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send subscription payment email', ['payment_id' => $this->id, 'error' => $e->getMessage()]);
            }
        }

        if ($this->payment_type === 'invoice' && $this->invoice) {
            $this->invoice->update([
                'status' => 'paid',
                'payment_method' => $this->payment_method,
                'payment_channel' => $this->payment_channel,
                'payment_reference' => $this->reference,
                'paid_at' => now(),
                'payment_id' => $this->id,
            ]);

            // Update PPP user status + lepas isolir jika sebelumnya terisolir
            if ($this->invoice->pppUser) {
                $pppUser = $this->invoice->pppUser;
                $wasIsolir = $pppUser->status_akun === 'isolir';

                $pppUser->update([
                    'status_bayar' => 'sudah_bayar',
                    'status_akun' => 'enable',
                ]);

                $pppUser->refresh();

                app(\App\Services\RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

                if ($wasIsolir) {
                    app(\App\Services\IsolirSynchronizer::class)->deisolate($pppUser);
                }
            }

            // Credit tenant wallet jika menggunakan platform gateway
            if ($this->via_platform_gateway && ! $this->wallet_transaction_id) {
                try {
                    app(\App\Services\TenantWalletService::class)->creditFromInvoicePayment($this);
                } catch (\Throwable $e) {
                    Log::error('Failed to credit tenant wallet from invoice payment', [
                        'payment_id' => $this->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function scopePending($query)
    {
        return $query->where($this->qualifyColumn('status'), 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where($this->qualifyColumn('status'), 'paid');
    }

    public function scopeForSubscription($query)
    {
        return $query->where($this->qualifyColumn('payment_type'), 'subscription');
    }

    public function scopeForInvoice($query)
    {
        return $query->where($this->qualifyColumn('payment_type'), 'invoice');
    }

    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}-{$date}-{$random}";
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp '.number_format($this->amount, 0, ',', '.');
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp '.number_format($this->total_amount, 0, ',', '.');
    }
}
