<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'type',
        'username',
        'plan_name',
        'amount',
        'tax_amount',
        'total',
        'status',
        'payment_method',
        'paid_at',
        'period_start',
        'period_end',
        'mixradius_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at'      => 'datetime',
            'period_start' => 'date',
            'period_end'   => 'date',
            'amount'       => 'decimal:2',
            'tax_amount'   => 'decimal:2',
            'total'        => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
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
}
