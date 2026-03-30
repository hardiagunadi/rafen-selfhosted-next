<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PppUser extends Model
{
    /** @use HasFactory<\Database\Factories\PppUserFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'status_registrasi',
        'tipe_pembayaran',
        'status_bayar',
        'status_akun',
        'owner_id',
        'ppp_profile_id',
        'tipe_service',
        'tagihkan_ppn',
        'prorata_otomatis',
        'promo_aktif',
        'durasi_promo_bulan',
        'biaya_instalasi',
        'jatuh_tempo',
        'aksi_jatuh_tempo',
        'tipe_ip',
        'profile_group_id',
        'odp_id',
        'ip_static',
        'odp_pop',
        'customer_id',
        'customer_name',
        'nik',
        'nomor_hp',
        'email',
        'alamat',
        'latitude',
        'longitude',
        'location_accuracy_m',
        'location_capture_method',
        'location_captured_at',
        'metode_login',
        'username',
        'ppp_password',
        'password_clientarea',
        'catatan',
        'assigned_teknisi_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tagihkan_ppn' => 'boolean',
            'prorata_otomatis' => 'boolean',
            'promo_aktif' => 'boolean',
            'jatuh_tempo' => 'date',
            'biaya_instalasi' => 'decimal:2',
            'location_accuracy_m' => 'decimal:2',
            'location_captured_at' => 'datetime',
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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PppProfile::class, 'ppp_profile_id');
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'subscribable_id')
                    ->where('subscribable_type', self::class);
    }

    public function cpeDevice(): HasOne
    {
        return $this->hasOne(CpeDevice::class);
    }

    /**
     * Scope for tenant data isolation
     */
    public function scopeAccessibleBy($query, User $user)
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
     * Generate unique customer_id for PPP users.
     * Format: 12-digit angka sequential (000000000001, 000000000002, ...)
     */
    public static function generateCustomerId(?int $ownerId = null): string
    {
        $max = static::query()
            ->whereRaw("customer_id REGEXP '^[0-9]{12}$'")
            ->selectRaw('MAX(CAST(customer_id AS UNSIGNED)) as max_num')
            ->value('max_num');
        $next = ($max ?? 0) + 1;

        return str_pad((string) $next, 12, '0', STR_PAD_LEFT);
    }

    /**
     * Hide sensitive credentials from non-super admins
     */
    public function getHiddenCredentialsAttribute(): array
    {
        $user = auth()->user();

        if ($user && $user->canViewPppCredentials()) {
            return [
                'username' => $this->username,
                'ppp_password' => $this->ppp_password,
                'password_clientarea' => $this->password_clientarea,
            ];
        }

        return [
            'username' => $this->username,
            'ppp_password' => '********',
            'password_clientarea' => '********',
        ];
    }

    /**
     * Get masked password for display
     */
    public function getMaskedPppPasswordAttribute(): string
    {
        $user = auth()->user();

        if ($user && $user->canViewPppCredentials()) {
            return $this->ppp_password ?? '';
        }

        return '********';
    }

    /**
     * Get masked client area password for display
     */
    public function getMaskedClientareaPasswordAttribute(): string
    {
        $user = auth()->user();

        if ($user && $user->canViewPppCredentials()) {
            return $this->password_clientarea ?? '';
        }

        return '********';
    }
}
