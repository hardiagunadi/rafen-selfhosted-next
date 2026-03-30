<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odp extends Model
{
    /** @use HasFactory<\Database\Factories\OdpFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'code',
        'name',
        'area',
        'latitude',
        'longitude',
        'capacity_ports',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'capacity_ports' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pppUsers(): HasMany
    {
        return $this->hasMany(PppUser::class, 'odp_id');
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
