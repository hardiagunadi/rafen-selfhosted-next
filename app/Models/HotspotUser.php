<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotUser extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'status_registrasi',
        'tipe_pembayaran',
        'status_bayar',
        'status_akun',
        'owner_id',
        'hotspot_profile_id',
        'profile_group_id',
        'tagihkan_ppn',
        'biaya_instalasi',
        'jatuh_tempo',
        'aksi_jatuh_tempo',
        'customer_id',
        'customer_name',
        'nik',
        'nomor_hp',
        'email',
        'alamat',
        'username',
        'metode_login',
        'hotspot_password',
        'catatan',
        'mixradius_id',
        'assigned_teknisi_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tagihkan_ppn'   => 'boolean',
            'jatuh_tempo'    => 'date',
            'biaya_instalasi' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedTeknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teknisi_id');
    }

    public function hotspotProfile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class);
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
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
            return $base->where(function ($q) use ($user) {
                $q->whereNull('assigned_teknisi_id')
                    ->orWhere('assigned_teknisi_id', $user->id);
            });
        }

        return $base;
    }

    /**
     * Generate unique customer_id for Hotspot users.
     * Format: MX-XXXXXX (sequential per owner, 6-digit zero-padded) — mengikuti format data existing.
     */
    public static function generateCustomerId(?int $ownerId = null): string
    {
        $prefix = 'MX-';
        $prefixLen = strlen($prefix) + 1;
        // Global sequence — tidak filter per owner agar tidak ada duplikat antar tenant
        $max = static::query()
            ->where('customer_id', 'like', $prefix.'%')
            ->selectRaw("MAX(CAST(SUBSTRING(customer_id, {$prefixLen}) AS UNSIGNED)) as max_num")
            ->value('max_num');
        $next = ($max ?? 0) + 1;
        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function getMaskedPasswordAttribute(): string
    {
        $user = auth()->user();

        if ($user && $user->canViewPppCredentials()) {
            return $this->hotspot_password ?? '';
        }

        return '********';
    }
}
