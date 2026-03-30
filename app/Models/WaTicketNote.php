<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaTicketNote extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'note',
        'image_path',
        'type',
        'meta',
        'read_by_cs',
    ];

    protected function casts(): array
    {
        return ['read_by_cs' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(WaTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
