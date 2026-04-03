<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const SUBSCRIPTION_METHOD_MONTHLY = 'monthly';

    public const SUBSCRIPTION_METHOD_LICENSE = 'license';

    public const LICENSE_DURATION_DAYS = 365;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
        'phone',
        'company_name',
        'address',
        'is_super_admin',
        'is_self_hosted_instance',
        'parent_id',
        'subscription_status',
        'subscription_expires_at',
        'subscription_plan_id',
        'subscription_method',
        'self_hosted_license_id',
        'self_hosted_instance_name',
        'self_hosted_fingerprint',
        'self_hosted_app_url',
        'license_max_mikrotik',
        'license_max_ppp_users',
        'vpn_username',
        'vpn_password',
        'vpn_ip',
        'vpn_enabled',
        'trial_days_remaining',
        'registered_at',
        'nickname',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'vpn_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'is_self_hosted_instance' => 'boolean',
            'subscription_expires_at' => 'date',
            'license_max_mikrotik' => 'integer',
            'license_max_ppp_users' => 'integer',
            'vpn_enabled' => 'boolean',
            'trial_days_remaining' => 'integer',
            'registered_at' => 'datetime',
        ];
    }

    // Relationships

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(TenantWallet::class, 'owner_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function tenantSettings(): HasOne
    {
        return $this->hasOne(TenantSettings::class);
    }

    public function waMultiSessionDevices(): HasMany
    {
        return $this->hasMany(WaMultiSessionDevice::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(WaTicket::class, 'assigned_to_id');
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function subUsers(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'subscribable_id')
            ->where('subscribable_type', self::class);
    }

    public function mikrotikConnections(): HasMany
    {
        return $this->hasMany(MikrotikConnection::class, 'owner_id');
    }

    public function oltConnections(): HasMany
    {
        return $this->hasMany(OltConnection::class, 'owner_id');
    }

    public function pppUsers(): HasMany
    {
        return $this->hasMany(PppUser::class, 'owner_id');
    }

    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class, 'owner_id');
    }

    public function pppProfiles(): HasMany
    {
        return $this->hasMany(PppProfile::class, 'owner_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'owner_id');
    }

    // Subscription helper methods

    public function isSuperAdmin(): bool
    {
        if ($this->role === 'teknisi') {
            return false;
        }

        return $this->is_super_admin === true;
    }

    public function isSubUser(): bool
    {
        return $this->parent_id !== null;
    }

    public function effectiveOwnerId(): int
    {
        return $this->parent_id ?? $this->id;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'administrator' || $this->isSuperAdmin();
    }

    public function isTeknisi(): bool
    {
        return $this->role === 'teknisi';
    }

    public function canViewPppCredentials(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isLicenseSubscription(): bool
    {
        return $this->subscription_method === self::SUBSCRIPTION_METHOD_LICENSE;
    }

    public function isSelfHostedInstance(): bool
    {
        return $this->is_self_hosted_instance === true;
    }

    public function resolveSubscriptionDurationDays(?SubscriptionPlan $plan = null, ?int $durationDays = null): int
    {
        if ($this->isLicenseSubscription()) {
            return self::LICENSE_DURATION_DAYS;
        }

        if ($durationDays !== null && $durationDays > 0) {
            return $durationDays;
        }

        return max(1, (int) ($plan?->duration_days ?? 30));
    }

    public function getEffectiveMikrotikLimit(): int
    {
        if ($this->isLicenseSubscription() && $this->license_max_mikrotik !== null) {
            return (int) $this->license_max_mikrotik;
        }

        return (int) ($this->subscriptionPlan?->max_mikrotik ?? -1);
    }

    public function getEffectivePppUsersLimit(): int
    {
        if ($this->isLicenseSubscription() && $this->license_max_ppp_users !== null) {
            return (int) $this->license_max_ppp_users;
        }

        return (int) ($this->subscriptionPlan?->max_ppp_users ?? -1);
    }

    public function getEffectiveVpnPeersLimit(): int
    {
        return (int) ($this->subscriptionPlan?->max_vpn_peers ?? -1);
    }

    public function hasReachedMikrotikLimit(?int $ownerId = null): bool
    {
        $limit = $this->getEffectiveMikrotikLimit();
        if ($limit < 0) {
            return false;
        }

        return MikrotikConnection::query()
            ->where('owner_id', $ownerId ?? $this->effectiveOwnerId())
            ->count() >= $limit;
    }

    public function hasReachedPppUsersLimit(?int $ownerId = null): bool
    {
        $limit = $this->getEffectivePppUsersLimit();
        if ($limit < 0) {
            return false;
        }

        return PppUser::query()
            ->where('owner_id', $ownerId ?? $this->effectiveOwnerId())
            ->count() >= $limit;
    }

    public function hasReachedVpnPeersLimit(?int $ownerId = null): bool
    {
        $limit = $this->getEffectiveVpnPeersLimit();
        if ($limit < 0) {
            return false;
        }

        return WgPeer::query()
            ->where('owner_id', $ownerId ?? $this->effectiveOwnerId())
            ->count() >= $limit;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active'
            && $this->subscription_expires_at
            && $this->subscription_expires_at->isFuture();
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial' && $this->trial_days_remaining > 0;
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_status === 'expired'
            || ($this->subscription_expires_at && $this->subscription_expires_at->isPast());
    }

    public function canAccessApp(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isSubUser()) {
            return $this->parent?->canAccessApp() ?? false;
        }

        return $this->hasActiveSubscription() || $this->isOnTrial();
    }

    public function getSubscriptionDaysRemaining(): int
    {
        if ($this->isOnTrial()) {
            return $this->trial_days_remaining;
        }

        if (! $this->subscription_expires_at) {
            return 0;
        }

        if ($this->subscription_expires_at->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->subscription_expires_at);
    }

    public function decrementTrialDays(): void
    {
        if ($this->isOnTrial() && $this->trial_days_remaining > 0) {
            $this->decrement('trial_days_remaining');

            if ($this->trial_days_remaining <= 0) {
                $this->update(['subscription_status' => 'expired']);
            }
        }
    }

    public function activateSubscription(SubscriptionPlan $plan, ?int $durationDays = null): void
    {
        $duration = $this->resolveSubscriptionDurationDays($plan, $durationDays);

        $this->update([
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays($duration),
        ]);
    }

    public function extendSubscription(int $days): void
    {
        $currentExpiry = $this->subscription_expires_at ?? now();

        if ($currentExpiry->isPast()) {
            $currentExpiry = now();
        }

        $this->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $currentExpiry->addDays($days),
        ]);
    }

    public function getSettings(): TenantSettings
    {
        return TenantSettings::getOrCreate($this->effectiveOwnerId());
    }

    public function isHotspotModuleEnabled(): bool
    {
        return $this->getSettings()->isHotspotModuleEnabled();
    }

    // Scopes

    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    public function scopeTenants($query)
    {
        return $query
            ->where('is_super_admin', false)
            ->whereNull('parent_id')
            ->where('role', 'administrator');
    }

    public function scopeActiveSubscribers($query)
    {
        return $query->tenants()
            ->where('subscription_status', 'active')
            ->where('subscription_expires_at', '>', now());
    }

    public function scopeExpiredSubscribers($query)
    {
        return $query->tenants()
            ->where(function ($subscriptionQuery) {
                $subscriptionQuery->where('subscription_status', 'expired')
                    ->orWhere('subscription_expires_at', '<', now());
            });
    }

    public function scopeTrialUsers($query)
    {
        return $query->tenants()
            ->where('subscription_status', 'trial')
            ->where('trial_days_remaining', '>', 0);
    }
}
