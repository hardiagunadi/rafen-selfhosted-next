<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaBlastLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'owner_id',
        'sent_by_id',
        'sent_by_name',
        'event',
        'provider',
        'phone',
        'phone_normalized',
        'status',
        'reason',
        'invoice_number',
        'invoice_id',
        'user_id',
        'username',
        'customer_name',
        'ref_id',
        'provider_message_id',
        'delivery_status',
        'pricing_metadata',
        'template_name',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'pricing_metadata' => 'array',
        ];
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
