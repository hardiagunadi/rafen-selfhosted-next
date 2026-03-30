<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiusAccount extends Model
{
    /** @use HasFactory<\Database\Factories\RadiusAccountFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'mikrotik_connection_id',
        'username',
        'password',
        'service',
        'ipv4_address',
        'rate_limit',
        'profile',
        'is_active',
        'notes',
        'uptime',
        'caller_id',
        'server_name',
        'bytes_in',
        'bytes_out',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function mikrotikConnection(): BelongsTo
    {
        return $this->belongsTo(MikrotikConnection::class);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            $impersonatingId = session('impersonating_tenant_id');
            if ($impersonatingId) {
                return $query->whereHas('mikrotikConnection', fn (Builder $q) => $q->where('owner_id', $impersonatingId));
            }
            return $query;
        }

        return $query->whereHas('mikrotikConnection', fn (Builder $q) => $q->where('owner_id', $user->effectiveOwnerId()));
    }
}
