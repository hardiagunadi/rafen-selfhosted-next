<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfileGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileGroupFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'owner',
        'owner_id',
        'mikrotik_connection_id',
        'type',
        'ip_pool_mode',
        'ip_pool_name',
        'ip_address',
        'netmask',
        'range_start',
        'range_end',
        'dns_servers',
        'parent_queue',
        'host_min',
        'host_max',
    ];

    public function mikrotikConnection(): BelongsTo
    {
        return $this->belongsTo(MikrotikConnection::class);
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
