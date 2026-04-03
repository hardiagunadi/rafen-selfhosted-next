<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfHostedUpdateRun extends Model
{
    protected $fillable = [
        'channel',
        'action',
        'target_version',
        'target_ref',
        'target_commit',
        'current_version',
        'current_commit',
        'started_at',
        'finished_at',
        'status',
        'triggered_by_user_id',
        'output_excerpt',
        'backup_path',
        'rollback_ref',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
