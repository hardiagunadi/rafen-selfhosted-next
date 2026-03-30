<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PppProfile extends Model
{
    /** @use HasFactory<\Database\Factories\PppProfileFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'owner_id',
        'harga_modal',
        'harga_promo',
        'ppn',
        'profile_group_id',
        'bandwidth_profile_id',
        'parent_queue',
        'masa_aktif',
        'satuan',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }

    public function bandwidthProfile(): BelongsTo
    {
        return $this->belongsTo(BandwidthProfile::class);
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
