<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'hotspot_profile_id',
        'profile_group_id',
        'batch_name',
        'code',
        'status',
        'username',
        'password',
        'used_at',
        'expired_at',
        'used_by_mac',
        'used_by_ip',
        'mixradius_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at'    => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hotspotProfile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class);
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }

    public function scopeUnused(Builder $query): Builder
    {
        return $query->where('status', 'unused');
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('status', 'used');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
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
