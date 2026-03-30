<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    protected $fillable = [
        'owner_id',
        'requester_id',
        'target_id',
        'from_schedule_id',
        'to_schedule_id',
        'reason',
        'status',
        'reviewed_by_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function fromSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class, 'from_schedule_id');
    }

    public function toSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class, 'to_schedule_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
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

        $base = $query->where('owner_id', $user->effectiveOwnerId());

        if ($user->isSubUser() && ! $user->isAdmin()) {
            return $base->where('requester_id', $user->id);
        }

        return $base;
    }
}
