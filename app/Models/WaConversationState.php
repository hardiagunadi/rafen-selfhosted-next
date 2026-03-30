<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaConversationState extends Model
{
    protected $fillable = [
        'conversation_id',
        'owner_id',
        'flow',
        'step',
        'collected',
        'expires_at',
    ];

    protected $casts = [
        'step'       => 'integer',
        'collected'  => 'array',
        'expires_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
