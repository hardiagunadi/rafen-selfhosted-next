<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotProfile extends Model
{
    /** @use HasFactory<\Database\Factories\HotspotProfileFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'owner_id',
        'harga_jual',
        'harga_promo',
        'ppn',
        'bandwidth_profile_id',
        'profile_type',
        'limit_type',
        'time_limit_value',
        'time_limit_unit',
        'quota_limit_value',
        'quota_limit_unit',
        'masa_aktif_value',
        'masa_aktif_unit',
        'profile_group_id',
        'parent_queue',
        'shared_users',
        'prioritas',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hotspotUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HotspotUser::class);
    }

    public function vouchers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function bandwidthProfile(): BelongsTo
    {
        return $this->belongsTo(BandwidthProfile::class);
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }

    /**
     * Hitung expired_at dari titik waktu tertentu berdasarkan masa_aktif profil.
     * Kembalikan null jika profil tidak punya masa_aktif.
     */
    public function computeExpiredAt(\Carbon\Carbon $from): ?\Carbon\Carbon
    {
        if (! $this->masa_aktif_value || ! $this->masa_aktif_unit) {
            return null;
        }

        return match ($this->masa_aktif_unit) {
            'menit'  => $from->copy()->addMinutes($this->masa_aktif_value),
            'jam'    => $from->copy()->addHours($this->masa_aktif_value),
            'hari'   => $from->copy()->addDays($this->masa_aktif_value),
            'bulan'  => $from->copy()->addMonths($this->masa_aktif_value),
            default  => null,
        };
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
}
