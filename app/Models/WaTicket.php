<?php

namespace App\Models;

use App\Models\HotspotUser;
use App\Models\PppUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaTicket extends Model
{
    protected $fillable = [
        'owner_id',
        'conversation_id',
        'manual_contact_name',
        'manual_contact_phone',
        'customer_type',
        'customer_id',
        'title',
        'description',
        'image_path',
        'type',
        'status',
        'priority',
        'assigned_to_id',
        'assigned_by_id',
        'resolved_at',
        'public_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (WaTicket $ticket) {
            if (empty($ticket->public_token)) {
                $ticket->public_token = bin2hex(random_bytes(16));
            }
        });
    }

    public function publicUrl(): string
    {
        if (empty($this->public_token)) {
            $this->public_token = bin2hex(random_bytes(16));
            $this->saveQuietly();
        }

        return url('/tiket/' . $this->public_token);
    }

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WaTicketNote::class, 'ticket_id')->orderBy('created_at');
    }

    /**
     * Pelanggan terkait (PppUser atau HotspotUser).
     * Menggunakan manual polymorphic sederhana via customer_type + customer_id.
     */
    public function customerModel(): ?Model
    {
        if (! $this->customer_type || ! $this->customer_id) {
            return null;
        }

        $map = [
            'ppp'     => PppUser::class,
            'hotspot' => HotspotUser::class,
        ];

        $class = $map[$this->customer_type] ?? null;

        return $class ? $class::find($this->customer_id) : null;
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

        $base = $query->where('owner_id', $user->effectiveOwnerId());

        if ($user->isTeknisi()) {
            return $base->where('assigned_to_id', $user->id);
        }

        return $base;
    }
}
