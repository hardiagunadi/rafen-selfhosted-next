<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'request_number',
        'owner_id',
        'amount',
        'status',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'processed_by',
        'processed_at',
        'admin_notes',
        'rejection_reason',
        'transfer_proof',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    public static function generateRequestNumber(): string
    {
        return 'WD-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }
}
