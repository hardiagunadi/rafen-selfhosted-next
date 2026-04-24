<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WaConversation extends Model
{
    protected $fillable = [
        'owner_id',
        'provider',
        'session_id',
        'provider_customer_key',
        'contact_phone',
        'contact_name',
        'assigned_to_id',
        'status',
        'bot_paused_until',
        'last_message',
        'last_message_at',
        'last_inbound_at',
        'service_window_expires_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'service_window_expires_at' => 'datetime',
            'bot_paused_until' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function hasOpenServiceWindow(): bool
    {
        return $this->service_window_expires_at !== null && $this->service_window_expires_at->isFuture();
    }

    public function isBotPaused(): bool
    {
        return $this->bot_paused_until !== null && $this->bot_paused_until->isFuture();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaChatMessage::class, 'conversation_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(WaTicket::class, 'conversation_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
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

    public function updateFromIncoming(string $message): void
    {
        $this->updateConversationState([
            'last_message' => mb_substr($message, 0, 500),
            'last_message_at' => now(),
            'last_inbound_at' => now(),
            'service_window_expires_at' => now()->addDay(),
            'status' => 'open',
            'unread_count' => $this->unread_count + 1,
        ]);
    }

    public function clearBotPause(): void
    {
        $this->update(['bot_paused_until' => null]);
    }

    public function pauseBotUntil(CarbonInterface $until): void
    {
        $this->update(['bot_paused_until' => $until]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateConversationState(array $attributes): void
    {
        $this->update($attributes);
    }
}
