<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaPlatformDeviceRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'device_id',
        'status',
        'reason',
        'notes',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaMultiSessionDevice::class, 'device_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
