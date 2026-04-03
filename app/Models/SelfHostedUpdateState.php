<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelfHostedUpdateState extends Model
{
    protected $fillable = [
        'channel',
        'current_version',
        'current_commit',
        'current_ref',
        'latest_version',
        'latest_commit',
        'latest_ref',
        'latest_published_at',
        'latest_manifest_url',
        'latest_release_notes_url',
        'update_available',
        'last_checked_at',
        'last_check_status',
        'last_check_message',
        'last_applied_at',
        'last_apply_status',
        'last_apply_message',
        'last_heartbeat_at',
        'last_successful_heartbeat_at',
        'last_heartbeat_status',
        'last_heartbeat_message',
        'rollback_ref',
        'last_heartbeat_status_id',
        'manifest_payload',
        'last_heartbeat_response',
    ];

    protected function casts(): array
    {
        return [
            'latest_published_at' => 'datetime',
            'update_available' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_applied_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_successful_heartbeat_at' => 'datetime',
            'manifest_payload' => 'array',
            'last_heartbeat_response' => 'array',
        ];
    }
}
