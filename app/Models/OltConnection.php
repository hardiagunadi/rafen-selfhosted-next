<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltConnection extends Model
{
    /** @use HasFactory<\Database\Factories\OltConnectionFactory> */
    use HasFactory;

    public const POLLING_RUNNING_PREFIX = '[RUNNING]';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'vendor',
        'name',
        'olt_model',
        'host',
        'snmp_port',
        'snmp_version',
        'snmp_community',
        'snmp_write_community',
        'snmp_timeout',
        'snmp_retries',
        'is_active',
        'oid_serial',
        'oid_onu_name',
        'oid_rx_onu',
        'oid_tx_onu',
        'oid_rx_olt',
        'oid_tx_olt',
        'oid_distance',
        'oid_status',
        'oid_reboot_onu',
        'last_polled_at',
        'last_poll_success',
        'last_poll_message',
        'cli_protocol',
        'cli_port',
        'cli_username',
        'cli_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snmp_port' => 'integer',
            'snmp_timeout' => 'integer',
            'snmp_retries' => 'integer',
            'is_active' => 'boolean',
            'last_polled_at' => 'datetime',
            'last_poll_success' => 'boolean',
            'cli_port' => 'integer',
            'cli_password' => 'encrypted',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function onuOptics(): HasMany
    {
        return $this->hasMany(OltOnuOptic::class);
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

    public function isPollingInProgress(): bool
    {
        $message = (string) ($this->last_poll_message ?? '');

        return str_starts_with($message, self::POLLING_RUNNING_PREFIX);
    }

    public function pollingProgressPercent(): ?int
    {
        if (! $this->isPollingInProgress()) {
            return null;
        }

        if (preg_match('/^\[RUNNING\]\s*(\d{1,3})%/', (string) $this->last_poll_message, $matches) !== 1) {
            return null;
        }

        return max(0, min(100, (int) $matches[1]));
    }

    public function pollingDisplayMessage(): ?string
    {
        $message = trim((string) ($this->last_poll_message ?? ''));

        if ($message === '') {
            return null;
        }

        if (! $this->isPollingInProgress()) {
            return $message;
        }

        $cleanedMessage = preg_replace('/^\[RUNNING\]\s*\d{1,3}%\s*/', '', $message);

        return trim((string) $cleanedMessage);
    }
}
