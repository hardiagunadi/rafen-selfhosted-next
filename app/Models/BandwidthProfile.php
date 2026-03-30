<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthProfile extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'upload_min_mbps',
        'upload_max_mbps',
        'download_min_mbps',
        'download_max_mbps',
        'owner',
        'owner_id',
    ];

    public function ownerUser(): BelongsTo
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
