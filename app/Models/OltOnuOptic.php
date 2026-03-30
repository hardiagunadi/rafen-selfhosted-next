<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltOnuOptic extends Model
{
    /** @use HasFactory<\Database\Factories\OltOnuOpticFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'olt_connection_id',
        'owner_id',
        'onu_index',
        'pon_interface',
        'onu_number',
        'serial_number',
        'onu_name',
        'distance_m',
        'rx_onu_dbm',
        'tx_onu_dbm',
        'rx_olt_dbm',
        'tx_olt_dbm',
        'status',
        'raw_payload',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_m' => 'integer',
            'rx_onu_dbm' => 'decimal:2',
            'tx_onu_dbm' => 'decimal:2',
            'rx_olt_dbm' => 'decimal:2',
            'tx_olt_dbm' => 'decimal:2',
            'raw_payload' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function oltConnection(): BelongsTo
    {
        return $this->belongsTo(OltConnection::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OltOnuOpticHistory::class, 'olt_onu_optic_id')->orderByDesc('polled_at');
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
