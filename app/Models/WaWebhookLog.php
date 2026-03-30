<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaWebhookLog extends Model
{
    protected $fillable = [
        'owner_id',
        'event_type',
        'session_id',
        'sender',
        'message',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function scopeMessages($query)
    {
        return $query->where('event_type', 'message');
    }

    public function scopeSessions($query)
    {
        return $query->where('event_type', 'session');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeAccessibleBy($query, User $user)
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
