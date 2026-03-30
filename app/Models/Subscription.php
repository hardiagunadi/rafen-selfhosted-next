<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'start_date',
        'end_date',
        'status',
        'amount_paid',
        'payment_reference',
        'payment_token',
        'activated_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount_paid' => 'decimal:2',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->end_date->isPast();
    }

    public function daysRemaining(): int
    {
        if ($this->end_date->isPast()) {
            return 0;
        }
        return now()->diffInDays($this->end_date);
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $this->user->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $this->end_date,
            'subscription_plan_id' => $this->subscription_plan_id,
        ]);
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function expire(): void
    {
        $this->update(['status' => 'expired']);

        $this->user->update([
            'subscription_status' => 'expired',
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public static function generatePaymentToken(): string
    {
        do {
            $token = \Illuminate\Support\Str::random(40);
        } while (static::where('payment_token', $token)->exists());

        return $token;
    }

    public function getOrCreatePaymentToken(): string
    {
        if (empty($this->payment_token)) {
            $this->update(['payment_token' => static::generatePaymentToken()]);
        }

        return $this->payment_token;
    }
}
