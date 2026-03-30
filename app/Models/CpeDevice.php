<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpeDevice extends Model
{
    protected $fillable = [
        'ppp_user_id',
        'owner_id',
        'genieacs_device_id',
        'param_profile',
        'serial_number',
        'manufacturer',
        'model',
        'firmware_version',
        'status',
        'last_seen_at',
        'cached_params',
        'olt_onu_optic_id',
        'mac_address',
    ];

    protected $casts = [
        'cached_params' => 'array',
        'last_seen_at'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function pppUser(): BelongsTo
    {
        return $this->belongsTo(PppUser::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function oltOnuOptic(): BelongsTo
    {
        return $this->belongsTo(OltOnuOptic::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isLinked(): bool
    {
        return $this->genieacs_device_id !== null;
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Update cached device info from a GenieACS device document.
     * Auto-detects TR-098 (InternetGatewayDevice) vs TR-181 (Device) parameter profile.
     */
    public function updateFromGenieacs(array $device): void
    {
        $client  = app(\App\Services\GenieAcsClient::class);
        $profile = $client->detectParamProfile($device);
        $params  = config("genieacs.params.{$profile}");

        $get = fn (string $key) => $client->extractValue($device, $params[$key] ?? '');

        $this->genieacs_device_id = $device['_id'] ?? $this->genieacs_device_id;
        $this->param_profile      = $profile;

        $this->serial_number    = $get('serial_number') ?? $device['_deviceId']['_SerialNumber'] ?? $this->serial_number;
        $this->manufacturer     = $get('manufacturer') ?? $device['_deviceId']['_Manufacturer'] ?? $this->manufacturer;
        $this->model            = $get('model') ?? $device['_deviceId']['_ProductClass'] ?? $this->model;
        $this->firmware_version = $get('firmware_version') ?? $this->firmware_version;

        // Status from _lastInform — use PeriodicInformInterval from device itself as threshold,
        // matching GenieACS UI logic. Fallback: config value (default 70 min).
        if (isset($device['_lastInform'])) {
            $lastInform = \Carbon\Carbon::parse($device['_lastInform']);

            // Try to read PeriodicInformInterval from the device document (in seconds)
            $periodicSec = $client->extractValue($device, 'InternetGatewayDevice.ManagementServer.PeriodicInformInterval')
                        ?? $client->extractValue($device, 'Device.ManagementServer.PeriodicInformInterval');

            $thresholdMinutes = $periodicSec
                ? (int) ceil($periodicSec / 60) + 5   // interval + 5-min buffer
                : (int) config('genieacs.online_threshold_minutes', 70);

            $this->status       = $lastInform->diffInMinutes(now()) < $thresholdMinutes ? 'online' : 'offline';
            $this->last_seen_at = $lastInform;
        }

        $wanConnections = $client->extractWanConnections($device);

        // Extract first available WAN MAC (used for OLT signal matching)
        $wanMac = null;
        foreach ($wanConnections as $conn) {
            if (! empty($conn['mac_address'])) {
                $wanMac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $conn['mac_address']));
                break;
            }
        }

        // Resolve MAC address for OLT linking:
        // Try WAN MAC first, then X_CU_SerialNumber (ZTE stores MAC here), then DeviceInfo MAC
        $macRaw = null;
        if ($wanMac) {
            $macRaw = $wanMac;
        } else {
            // X_CU_SerialNumber on ZTE devices contains the MAC address
            $xcuSerial = $client->extractValue($device, 'InternetGatewayDevice.DeviceInfo.X_CU_SerialNumber');
            if ($xcuSerial) {
                $macRaw = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $xcuSerial));
            }
        }
        // Normalize to aa:bb:cc:dd:ee:ff format
        if ($macRaw && strlen($macRaw) === 12) {
            $this->mac_address = strtolower(implode(':', str_split($macRaw, 2)));
        }

        $this->cached_params = [
            'profile'          => $profile,
            'wifi_ssid'        => $get('wifi_ssid'),
            'pppoe_username'   => $get('pppoe_username'),
            'uptime'           => $get('uptime'),
            'inform_interval'  => $periodicSec ? (int) $periodicSec : null,
            'wifi_networks'    => $client->extractWifiNetworks($device),
            'wan_connections'  => $wanConnections,
            'wan_mac'          => $wanMac,
        ];
    }
}
