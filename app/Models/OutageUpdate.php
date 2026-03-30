<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutageUpdate extends Model
{
    protected $fillable = [
        'outage_id',
        'user_id',
        'type',
        'body',
        'meta',
        'image_path',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function outage(): BelongsTo
    {
        return $this->belongsTo(Outage::class, 'outage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
