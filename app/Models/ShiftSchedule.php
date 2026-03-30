<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftSchedule extends Model
{
    protected $fillable = [
        'owner_id',
        'user_id',
        'shift_definition_id',
        'schedule_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shiftDefinition(): BelongsTo
    {
        return $this->belongsTo(ShiftDefinition::class, 'shift_definition_id');
    }

    public function swapRequests(): HasMany
    {
        return $this->hasMany(ShiftSwapRequest::class, 'from_schedule_id');
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
            return $base->where('user_id', $user->id);
        }

        return $base;
    }
}
