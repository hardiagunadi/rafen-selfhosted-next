<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMultiSessionDevice extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'wa_number',
        'device_name',
        'is_default',
        'is_platform_device',
        'is_active',
        'last_status',
        'last_seen_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_platform_device' => 'boolean',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('user_id', $ownerId);
    }
}
