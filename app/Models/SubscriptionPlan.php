<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'duration_days',
        'max_mikrotik',
        'max_ppp_users',
        'max_vpn_peers',
        'features',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'max_mikrotik' => 'integer',
            'max_ppp_users' => 'integer',
            'max_vpn_peers' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isUnlimitedMikrotik(): bool
    {
        return $this->max_mikrotik === -1;
    }

    public function isUnlimitedPppUsers(): bool
    {
        return $this->max_ppp_users === -1;
    }

    public function isUnlimitedVpnPeers(): bool
    {
        return $this->max_vpn_peers === -1;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}
