<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Outage extends Model
{
    const STATUS_OPEN        = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED    = 'resolved';

    const SEVERITY_LOW      = 'low';
    const SEVERITY_MEDIUM   = 'medium';
    const SEVERITY_HIGH     = 'high';
    const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'status',
        'severity',
        'started_at',
        'estimated_resolved_at',
        'resolved_at',
        'assigned_teknisi_id',
        'public_token',
        'wa_blast_sent_at',
        'wa_blast_count',
        'resolution_wa_sent_at',
        'created_by_id',
        'include_status_link',
    ];

    protected function casts(): array
    {
        return [
            'started_at'            => 'datetime',
            'estimated_resolved_at' => 'datetime',
            'resolved_at'           => 'datetime',
            'wa_blast_sent_at'      => 'datetime',
            'resolution_wa_sent_at' => 'datetime',
            'wa_blast_count'        => 'integer',
            'include_status_link'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Outage $outage) {
            if (empty($outage->public_token)) {
                $outage->public_token = bin2hex(random_bytes(16));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedTeknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teknisi_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function affectedAreas(): HasMany
    {
        return $this->hasMany(OutageAffectedArea::class, 'outage_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(OutageUpdate::class, 'outage_id')->orderBy('created_at');
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function affectedOdpIds(): array
    {
        return $this->affectedAreas
            ->where('area_type', 'odp')
            ->pluck('odp_id')
            ->filter()
            ->values()
            ->all();
    }

    public function affectedKeywords(): array
    {
        return $this->affectedAreas
            ->where('area_type', 'keyword')
            ->pluck('label')
            ->filter()
            ->values()
            ->all();
    }

    public function affectedNasIds(): array
    {
        return $this->affectedAreas
            ->where('area_type', 'nas')
            ->pluck('nas_id')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve ProfileGroup IDs dari NAS yang terdampak.
     * Jika ProfileGroup tidak terhubung ke NAS, return [] (gunakan resolveNasUsernames sebagai fallback).
     */
    protected function affectedNasProfileGroupIds(): array
    {
        $nasIds = $this->affectedNasIds();
        if (empty($nasIds)) {
            return [];
        }

        return ProfileGroup::whereIn('mikrotik_connection_id', $nasIds)
            ->pluck('id')
            ->all();
    }

    /**
     * Resolve PPP usernames via radacct jika ProfileGroup tidak punya relasi NAS.
     */
    protected function resolveNasUsernames(): array
    {
        $nasIds = $this->affectedNasIds();
        if (empty($nasIds)) {
            return [];
        }

        // Jika ProfileGroup punya data, tidak perlu fallback
        $profileGroupIds = $this->affectedNasProfileGroupIds();
        if (! empty($profileGroupIds)) {
            return [];
        }

        // Fallback via radacct.nasipaddress = MikrotikConnection.host
        $hosts = MikrotikConnection::whereIn('id', $nasIds)->pluck('host')->all();
        if (empty($hosts)) {
            return [];
        }

        return DB::table('radacct')
            ->whereIn('nasipaddress', $hosts)
            ->distinct()
            ->pluck('username')
            ->all();
    }

    public function affectedAreaLabels(): array
    {
        return $this->affectedAreas
            ->map(fn ($a) => $a->display_label)
            ->filter()
            ->values()
            ->all();
    }

    public function affectedPppUsers(): Builder
    {
        $odpIds          = $this->affectedOdpIds();
        $keywords        = $this->affectedKeywords();
        $profileGroupIds = $this->affectedNasProfileGroupIds();
        $nasUsernames    = $this->resolveNasUsernames();

        $query = PppUser::query()
            ->distinct()
            ->where('owner_id', $this->owner_id)
            ->where('status_akun', 'enable')
            ->whereNotNull('nomor_hp')
            ->where('nomor_hp', '!=', '');

        $hasCondition = ! empty($odpIds) || ! empty($keywords) || ! empty($profileGroupIds) || ! empty($nasUsernames);

        if ($hasCondition) {
            $query->where(function ($q) use ($odpIds, $keywords, $profileGroupIds, $nasUsernames) {
                if (! empty($odpIds)) {
                    $q->orWhereIn('odp_id', $odpIds);
                }
                if (! empty($profileGroupIds)) {
                    $q->orWhereIn('profile_group_id', $profileGroupIds);
                }
                if (! empty($nasUsernames)) {
                    $q->orWhereIn('username', $nasUsernames);
                }
                foreach ($keywords as $kw) {
                    $q->orWhere('alamat', 'LIKE', '%'.$kw.'%');
                }
            });
        }

        return $query;
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
            return $base->where('assigned_teknisi_id', $user->id);
        }

        return $base;
    }
}
