<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_number',
        'ppp_user_id',
        'ppp_profile_id',
        'owner_id',
        'customer_id',
        'customer_name',
        'tipe_service',
        'paket_langganan',
        'harga_dasar',
        'harga_asli',
        'ppn_percent',
        'ppn_amount',
        'total',
        'promo_applied',
        'prorata_applied',
        'due_date',
        'status',
        'renewed_without_payment',
        'payment_method',
        'payment_channel',
        'payment_reference',
        'paid_at',
        'payment_id',
        'paid_by',
        'cash_received',
        'transfer_amount',
        'payment_note',
        'payment_token',
        'nota_printed_at',
        'nota_printed_by',
    ];

    protected function casts(): array
    {
        return [
            'promo_applied' => 'boolean',
            'prorata_applied' => 'boolean',
            'renewed_without_payment' => 'boolean',
            'harga_asli' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'nota_printed_at' => 'datetime',
        ];
    }

    public function pppUser(): BelongsTo
    {
        return $this->belongsTo(PppUser::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PppProfile::class, 'ppp_profile_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function notaPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nota_printed_by');
    }

    public function hasBeenNotaPrinted(): bool
    {
        return $this->nota_printed_at !== null;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function isOverdue(): bool
    {
        return $this->isUnpaid() && $this->due_date && $this->due_date->isPast();
    }

    public function hasDueDate(?CarbonInterface $dueDate): bool
    {
        return $this->due_date !== null
            && $dueDate !== null
            && $this->due_date->isSameDay($dueDate);
    }

    public function isCurrentBillingInvoice(?CarbonInterface $currentDueDate = null): bool
    {
        if (! $this->isUnpaid() || $this->due_date === null) {
            return false;
        }

        return $this->due_date->isSameMonth($this->resolveInvoiceContextReferenceDate($currentDueDate));
    }

    public function isHistoricalUnpaid(?CarbonInterface $currentDueDate = null): bool
    {
        return $this->isUnpaid()
            && $this->due_date !== null
            && $this->due_date->lt($this->resolveInvoiceContextReferenceDate($currentDueDate)->copy()->startOfMonth());
    }

    private function resolveInvoiceContextReferenceDate(?CarbonInterface $currentDueDate = null): CarbonInterface
    {
        $today = now()->endOfDay();

        if ($currentDueDate !== null && $currentDueDate->greaterThan($today)) {
            return $currentDueDate->copy()->endOfDay();
        }

        return $today;
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            $impersonatingId = session('impersonating_tenant_id');
            if ($impersonatingId) {
                return $query->where('owner_id', $impersonatingId);
            }

            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp '.number_format($this->total, 0, ',', '.');
    }

    /**
     * Generate nomor invoice berurutan per-tenant per-bulan.
     * Format: PREFIX-YYYYMMnnnn  contoh: INV-2026030001
     */
    public static function generatePaymentToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function generateNumber(int $ownerId, string $prefix): string
    {
        return DB::transaction(function () use ($ownerId, $prefix) {
            $yearMonth = now()->format('Ym');
            $pattern = $prefix.'-'.$yearMonth.'%';

            $last = static::where('owner_id', $ownerId)
                ->where('invoice_number', 'like', $pattern)
                ->lockForUpdate()
                ->max('invoice_number');

            $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix.'-'.$yearMonth.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
