<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WgPeer extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'mikrotik_connection_id',
        'name',
        'public_key',
        'private_key',
        'preshared_key',
        'vpn_ip',
        'extra_allowed_ips',
        'is_active',
        'last_synced_at',
    ];

    public function scopeAccessibleBy(\Illuminate\Database\Eloquent\Builder $query, \App\Models\User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        $query->where('owner_id', $user->effectiveOwnerId());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function mikrotikConnection(): BelongsTo
    {
        return $this->belongsTo(MikrotikConnection::class);
    }
}
