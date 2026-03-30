<?php

namespace App\Services;

use App\Models\TenantSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenieAcsClient
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private int $timeout;

    private string $crUsername;

    private string $crPassword;

    /** Prefix untuk nama preset di GenieACS agar unik per-tenant. */
    private string $presetPrefix;

    public function __construct(
        ?string $url = null,
        ?string $username = null,
        ?string $password = null,
        ?string $crUsername = null,
        ?string $crPassword = null,
        ?string $presetPrefix = null,
    ) {
        $this->baseUrl = rtrim($url ?? config('genieacs.nbi_url', 'http://localhost:7557'), '/');
        $this->username = $username ?? config('genieacs.username', '');
        $this->password = $password ?? config('genieacs.password', '');
        $this->timeout = config('genieacs.timeout', 10);
        $this->crUsername = $crUsername ?? config('genieacs.connection_request_username', 'rafen');
        $this->crPassword = $crPassword ?? config('genieacs.connection_request_password', 'rafen2024');
        // Prefix preset: gunakan yang diberikan, atau hash 6 karakter dari crUsername
        // agar preset setiap tenant tidak saling overwrite di GenieACS yang sama.
        $this->presetPrefix = $presetPrefix ?? substr(md5($this->crUsername), 0, 6).'_';
    }

    /**
     * Create a GenieAcsClient from a TenantSettings model.
     * Falls back to global .env config when tenant has no GenieACS URL configured.
     * CR credentials are always resolved from tenant settings (with auto-generated fallback).
     */
    public static function fromTenantSettings(TenantSettings $settings): self
    {
        $crUsername = $settings->resolvedCrUsername();
        // Prefix preset berbasis user_id tenant agar unik dan stabil (tidak berubah meski password berubah)
        $presetPrefix = $settings->user_id ? 't'.$settings->user_id.'_' : null;

        if ($settings->hasGenieacsConfigured()) {
            return new self(
                $settings->genieacs_url,
                $settings->genieacs_username ?? '',
                $settings->genieacs_password ?? '',
                $crUsername,
                $settings->resolvedCrPassword(),
                $presetPrefix,
            );
        }

        return new self(
            null, null, null,
            $crUsername,
            $settings->resolvedCrPassword(),
            $presetPrefix,
        );
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Find a GenieACS device by its PPPoE username.
     * Searches both IGD (TR-098) and Device (TR-181) parameter paths.
     */
    public function findDeviceByUsername(string $username): ?array
    {
        $igdPath = config('genieacs.params.igd.pppoe_username');
        $devicePath = config('genieacs.params.device.pppoe_username');

        // Try IGD path first (most common for ONUs/XPONs)
        foreach ([$igdPath, $devicePath] as $path) {
            $key = $path.'._value';
            $query = json_encode([$key => $username]);
            $response = $this->get('/devices/', ['query' => $query]);

            if ($response->successful()) {
                $devices = $response->json();
                if (! empty($devices)) {
                    return $devices[0];
                }
            }
        }

        // Fallback: search all devices and match manually (for devices with non-standard paths)
        Log::info("GenieACS: device with PPPoE username '{$username}' not found via standard paths.");

        return null;
    }

    /**
     * Detect parameter root: 'igd' or 'device' based on device document.
     */
    public function detectParamProfile(array $device): string
    {
        if (isset($device['InternetGatewayDevice'])) {
            return 'igd';
        }

        return 'device';
    }

    /**
     * Get a named parameter value from a device document.
     * Handles both IGD and Device root trees.
     */
    public function getParamValue(array $device, string $paramKey): mixed
    {
        $profile = $this->detectParamProfile($device);
        $path = config("genieacs.params.{$profile}.{$paramKey}");

        if ($path) {
            $value = $this->extractValue($device, $path);
            if ($value !== null) {
                return $value;
            }
        }

        // Fallback: _deviceId is always populated by GenieACS from every Inform
        $deviceIdFallback = [
            'manufacturer' => '_Manufacturer',
            'model' => '_ProductClass',
            'serial_number' => '_SerialNumber',
        ];

        if (isset($deviceIdFallback[$paramKey])) {
            return $device['_deviceId'][$deviceIdFallback[$paramKey]] ?? null;
        }

        return null;
    }

    /**
     * Get device info document from GenieACS.
     */
    public function getDeviceInfo(string $deviceId): array
    {
        $response = $this->get('/devices/', ['query' => json_encode(['_id' => $deviceId])]);

        if (! $response->successful()) {
            Log::warning('GenieACS: getDeviceInfo failed', [
                'deviceId' => $deviceId,
                'status' => $response->status(),
            ]);

            return [];
        }

        $body = $response->json();

        return is_array($body) && ! empty($body) ? $body[0] : [];
    }

    /**
     * Create a reboot task for a device.
     */
    public function rebootDevice(string $deviceId): array
    {
        return $this->createTask($deviceId, ['name' => 'reboot']);
    }

    /**
     * Create a factory reset task for a device.
     */
    public function factoryReset(string $deviceId): array
    {
        return $this->createTask($deviceId, ['name' => 'factoryReset']);
    }

    /**
     * Set WiFi SSID and password on a device.
     * Auto-detects TR-098 vs TR-181 parameter paths.
     */
    public function setWifi(string $deviceId, string $ssid, ?string $password = null, string $profile = 'igd'): array
    {
        $params = config("genieacs.params.{$profile}");
        $parameterValues = [
            [$params['wifi_ssid'], $ssid, 'xsd:string'],
        ];

        if (is_string($password) && $password !== '') {
            $parameterValues[] = [$params['wifi_password'], $password, 'xsd:string'];
        }

        return $this->createTask($deviceId, [
            'name' => 'setParameterValues',
            'parameterValues' => $parameterValues,
        ]);
    }

    /**
     * Set PPPoE credentials on a device.
     */
    public function setPppoeCredentials(string $deviceId, string $username, string $password, string $profile = 'igd'): array
    {
        $params = config("genieacs.params.{$profile}");

        return $this->createTask($deviceId, [
            'name' => 'setParameterValues',
            'parameterValues' => [
                [$params['pppoe_username'], $username, 'xsd:string'],
                [$params['pppoe_password'], $password, 'xsd:string'],
            ],
        ]);
    }

    /**
     * Set arbitrary parameter values on a device.
     * $params = [['path.to.param', 'value', 'xsd:string'], ...]
     */
    public function setParameterValues(string $deviceId, array $params): array
    {
        return $this->createTask($deviceId, [
            'name' => 'setParameterValues',
            'parameterValues' => $params,
        ]);
    }

    /**
     * Create or update a GenieACS preset (applies to all devices on next inform).
     * PUT /presets/{name}
     */
    public function putPreset(string $name, array $body): bool
    {
        $response = $this->put('/presets/'.rawurlencode($name), $body);

        if (! $response->successful()) {
            Log::warning('GenieACS: putPreset failed', [
                'name' => $name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }

    /**
     * Ensure the two default presets exist in GenieACS:
     *  - set-cr-creds: sets CR username/password so GenieACS can trigger Connection
     *    Request and force immediate task execution on every device.
     *  - set-inform-interval: reduces PeriodicInformInterval to 5 min so tasks
     *    are delivered promptly even if Connection Request fails.
     *
     * Preset names di-prefix dengan $presetPrefix agar unik per-tenant sehingga
     * tidak saling overwrite jika beberapa tenant berbagi satu instans GenieACS.
     */
    public function ensureDefaultPresets(): void
    {
        $username = $this->crUsername;
        $password = $this->crPassword;
        $interval = (string) config('genieacs.inform_interval', 300);
        $p = $this->presetPrefix;

        // CR credentials — TR-098 (InternetGatewayDevice root)
        // Tanpa precondition agar GenieACS selalu memastikan credentials ter-set
        // ke setiap device, bahkan jika belum pernah dibaca sebelumnya.
        $this->putPreset($p.'set-cr-creds-igd', [
            'weight' => 100,
            'precondition' => '',
            'configurations' => [
                ['type' => 'value', 'name' => 'InternetGatewayDevice.ManagementServer.ConnectionRequestUsername', 'value' => $username],
                ['type' => 'value', 'name' => 'InternetGatewayDevice.ManagementServer.ConnectionRequestPassword', 'value' => $password],
            ],
        ]);

        // CR credentials — TR-181 (Device root)
        $this->putPreset($p.'set-cr-creds-device', [
            'weight' => 100,
            'precondition' => '',
            'configurations' => [
                ['type' => 'value', 'name' => 'Device.ManagementServer.ConnectionRequestUsername', 'value' => $username],
                ['type' => 'value', 'name' => 'Device.ManagementServer.ConnectionRequestPassword', 'value' => $password],
            ],
        ]);

        // Inform interval — TR-098
        // Gunakan $ne (tidak sama dengan) agar modem baru dengan interval 3600s
        // langsung diperbarui ke 300s tanpa menunggu 1 jam inform pertama.
        $this->putPreset($p.'set-inform-interval', [
            'weight' => 100,
            'precondition' => json_encode(['InternetGatewayDevice.ManagementServer.PeriodicInformInterval' => ['$ne' => (int) $interval]]),
            'configurations' => [
                ['type' => 'value', 'name' => 'InternetGatewayDevice.ManagementServer.PeriodicInformInterval', 'value' => $interval],
            ],
        ]);

        // Inform interval — TR-181
        $this->putPreset($p.'set-inform-interval-device', [
            'weight' => 100,
            'precondition' => json_encode(['Device.ManagementServer.PeriodicInformInterval' => ['$ne' => (int) $interval]]),
            'configurations' => [
                ['type' => 'value', 'name' => 'Device.ManagementServer.PeriodicInformInterval', 'value' => $interval],
            ],
        ]);
    }

    /**
     * Refresh a device object tree (forces GenieACS to re-fetch params from CPE).
     */
    public function refreshObject(string $deviceId, string $objectPath = 'InternetGatewayDevice'): array
    {
        return $this->createTask($deviceId, [
            'name' => 'refreshObject',
            'objectName' => $objectPath,
        ]);
    }

    /**
     * Create an arbitrary task on a device.
     * Returns ['queued' => bool, 'task_id' => string|null, 'status' => int]
     */
    public function createTask(string $deviceId, array $taskBody, bool $connectionRequest = true): array
    {
        // connection_request=true → GenieACS sends HTTP connection request to CPE
        // to force an immediate TR-069 session so the task executes right away.
        // timeout=15000 → wait up to 15s for CPE to connect and execute the task.
        // HTTP 200 = task executed immediately, HTTP 202 = queued (CPE unreachable).
        $query = $connectionRequest
            ? 'timeout=15000&connection_request'
            : 'timeout=0';

        $response = $this->post(
            '/devices/'.rawurlencode($deviceId).'/tasks?'.$query,
            $taskBody,
            $connectionRequest ? 20 : null
        );

        $status = $response->status();

        if ($status === 404) {
            throw new RuntimeException("Device '{$deviceId}' not found in GenieACS.");
        }

        if (! in_array($status, [200, 202])) {
            Log::error('GenieACS: createTask failed', [
                'deviceId' => $deviceId,
                'task' => $taskBody,
                'status' => $status,
                'body' => $response->body(),
            ]);
            throw new RuntimeException("GenieACS task failed with HTTP {$status}: ".$response->body());
        }

        $body = $response->json() ?? [];
        $taskId = $body['_id'] ?? null;

        return [
            'queued' => $status === 202,
            'task_id' => $taskId,
            'status' => $status,
        ];
    }

    /**
     * Get a task by its ID.
     */
    public function getTask(string $taskId): ?array
    {
        $query = json_encode(['_id' => $taskId]);
        $response = $this->get('/tasks/', ['query' => $query]);

        if (! $response->successful()) {
            return null;
        }

        $tasks = $response->json();

        return ! empty($tasks) ? $tasks[0] : null;
    }

    /**
     * List pending tasks from GenieACS.
     */
    public function getTasks(int $limit = 200): array
    {
        $response = $this->get('/tasks', ['limit' => $limit]);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * List faults (failed tasks) from GenieACS.
     */
    public function getFaults(int $limit = 200): array
    {
        $response = $this->get('/faults', ['limit' => $limit]);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /**
     * List all devices.
     */
    public function listDevices(array $queryFilter = []): array
    {
        $params = [];
        if (! empty($queryFilter)) {
            $params['query'] = json_encode($queryFilter);
        }

        $response = $this->get('/devices/', $params);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    // -------------------------------------------------------------------------
    // Value extraction helpers
    // -------------------------------------------------------------------------

    /**
     * Extract a value from a GenieACS device document by dotted path.
     * e.g. "InternetGatewayDevice.DeviceInfo.SoftwareVersion" → "_value"
     */
    public function extractValue(array $doc, string $path): mixed
    {
        $parts = explode('.', $path);
        $cursor = $doc;

        foreach ($parts as $index => $part) {
            if (isset($cursor[$part])) {
                $cursor = $cursor[$part];

                continue;
            }

            // Part not found — try iterating numeric instance keys (e.g. device uses "2" instead of "1")
            $numericKeys = array_values(array_filter(array_keys($cursor), 'is_numeric'));
            if (empty($numericKeys)) {
                return null;
            }

            sort($numericKeys);
            $remaining = implode('.', array_slice($parts, $index + 1));

            foreach ($numericKeys as $key) {
                if (! isset($cursor[$key])) {
                    continue;
                }
                $candidate = $remaining !== ''
                    ? $this->extractValue($cursor[$key], $remaining)
                    : ($cursor[$key]['_value'] ?? null);
                if ($candidate !== null) {
                    return $candidate;
                }
            }

            return null;
        }

        return $cursor['_value'] ?? null;
    }

    /**
     * Extract all WiFi network instances from device doc.
     * Supports both IGD (TR-098) and Device (TR-181) profiles.
     * Returns array of networks with ssid/password/enabled/band fields.
     */
    public function extractWifiNetworks(array $doc): array
    {
        // TR-098 (InternetGatewayDevice)
        if (isset($doc['InternetGatewayDevice'])) {
            $lanDevice = $doc['InternetGatewayDevice']['LANDevice']['1'] ?? [];
            $wlanConfs = $lanDevice['WLANConfiguration'] ?? [];
            $networks = [];

            foreach ($wlanConfs as $idx => $wlan) {
                if (! is_numeric($idx)) {
                    continue;
                }
                $get = fn (string $key) => $wlan[$key]['_value'] ?? null;

                // Enable: null means not yet fetched from CPE — assume true (default on)
                $enableVal = $get('Enable');
                $enabled = $enableVal === null ? true : (bool) $enableVal;

                // Detect band: X_CT-COM_RFBand (0=2.4GHz, 1=5GHz) is most reliable on CT-COM devices,
                // fallback to Standard field (a/ac/ax = 5GHz), then channel (>=36 = 5GHz)
                $standard = $get('Standard') ?? '';
                $channel = (int) ($get('Channel') ?? 0);
                $rfBand = $get('X_CT-COM_RFBand');
                if ($rfBand !== null) {
                    $band = ((int) $rfBand === 1) ? '5GHz' : '2.4GHz';
                } elseif (str_contains($standard, 'ac') || str_contains($standard, 'ax') || preg_match('/\ba\b/', $standard)) {
                    $band = '5GHz';
                } elseif ($channel >= 36) {
                    $band = '5GHz';
                } else {
                    $band = '2.4GHz';
                }

                $networks[(int) $idx] = [
                    'index' => (int) $idx,
                    'ssid' => $get('SSID'),
                    'password' => $get('KeyPassphrase'),
                    'enabled' => $enabled,
                    'channel' => $get('Channel'),
                    'standard' => $standard,
                    'encryption' => $get('IEEE11iEncryptionModes'),
                    'band' => $band,
                    'path' => "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}",
                ];
            }

            ksort($networks);

            return array_values($networks);
        }

        // TR-181 (Device.WiFi.SSID.* + Device.WiFi.AccessPoint.* + Device.WiFi.Radio.*)
        $ssids = $doc['Device']['WiFi']['SSID'] ?? [];
        $aps = $doc['Device']['WiFi']['AccessPoint'] ?? [];
        $radios = $doc['Device']['WiFi']['Radio'] ?? [];
        $networks = [];

        foreach ($ssids as $idx => $ssid) {
            if (! is_numeric($idx)) {
                continue;
            }
            $get = fn (string $key) => $ssid[$key]['_value'] ?? null;
            $ap = $aps[$idx] ?? [];
            $security = $ap['Security'] ?? [];
            $getAp = fn (string $key) => $security[$key]['_value'] ?? null;

            // Resolve radio index via LowerLayers (e.g. "Device.WiFi.Radio.1.")
            $lowerLayers = $get('LowerLayers') ?? '';
            $radioIdx = preg_match('/Radio\.(\d+)/', $lowerLayers, $m) ? (int) $m[1] : (int) $idx;
            $radio = $radios[$radioIdx] ?? [];
            $getRadio = fn (string $key) => $radio[$key]['_value'] ?? null;

            $channel = (int) ($getRadio('Channel') ?? 0);
            $freqBand = $getRadio('OperatingFrequencyBand') ?? '';
            $standard = $getRadio('OperatingStandards') ?? '';
            $band = (str_contains($freqBand, '5') || $channel >= 36) ? '5GHz' : '2.4GHz';

            $enableVal = $get('Enable');
            $enabled = $enableVal === null ? true : (bool) $enableVal;

            $networks[(int) $idx] = [
                'index' => (int) $idx,
                'ssid' => $get('SSID'),
                'password' => $getAp('KeyPassphrase'),
                'enabled' => $enabled,
                'channel' => $getRadio('Channel'),
                'standard' => $standard,
                'encryption' => $getAp('ModeEnabled'),
                'band' => $band,
                'path' => "Device.WiFi.SSID.{$idx}",
                'ap_path' => "Device.WiFi.AccessPoint.{$idx}",
            ];
        }

        ksort($networks);

        return array_values($networks);
    }

    /**
     * Extract all WAN connections from device doc.
     * Supports both IGD (TR-098) and Device (TR-181) profiles.
     * Returns array of connections with name/status/type/vlan/username/ip fields.
     */
    public function extractWanConnections(array $doc): array
    {
        // TR-181: Device.PPP.Interface.*
        if (! isset($doc['InternetGatewayDevice'])) {
            $pppIfaces = $doc['Device']['PPP']['Interface'] ?? [];
            $connections = [];

            foreach ($pppIfaces as $idx => $iface) {
                if (! is_numeric($idx)) {
                    continue;
                }
                $get = fn (string $key) => $iface[$key]['_value'] ?? null;
                $externalIp = $iface['IPCP']['LocalIPAddress']['_value'] ?? null;

                $connections[] = [
                    'wan_idx' => (int) $idx,
                    'cd_idx' => 1,
                    'ppp_idx' => (int) $idx,
                    'key' => "ppp{$idx}",
                    'name' => $get('Name') ?? $get('Alias') ?? "PPP {$idx}",
                    'enabled' => $get('Enable'),
                    'status' => $get('ConnectionStatus'),
                    'connection_type' => 'PPPoE',
                    'transport_type' => 'PPP',
                    'username' => $get('Username'),
                    'external_ip' => $externalIp,
                    'remote_ip' => null,
                    'uptime' => $get('Uptime'),
                    'nat_enabled' => null,
                    'dns_servers' => null,
                    'service_list' => null,
                    'lan_interface' => null,
                    'vlan_id' => null,
                    'vlan_prio' => null,
                    'vlan_mode' => null,
                    'mac_address' => $get('MACAddress'),
                    'path_prefix' => "Device.PPP.Interface.{$idx}",
                    'vlan_path_prefix' => null,
                ];
            }

            return $connections;
        }

        // TR-098: InternetGatewayDevice.WANDevice.*
        $wanDevices = $doc['InternetGatewayDevice']['WANDevice'] ?? [];
        $connections = [];

        foreach ($wanDevices as $wdIdx => $wanDev) {
            if (! is_numeric($wdIdx)) {
                continue;
            }
            $wanCds = $wanDev['WANConnectionDevice'] ?? [];

            foreach ($wanCds as $cdIdx => $cd) {
                if (! is_numeric($cdIdx)) {
                    continue;
                }

                // VLAN from WANEponLinkConfig
                $eponCfg = $cd['X_CT-COM_WANEponLinkConfig'] ?? $cd['X_CT-COM_WANGponLinkConfig'] ?? [];
                $vlan = $eponCfg['VLANIDMark']['_value'] ?? null;
                $mode = $eponCfg['Mode']['_value'] ?? null;
                $prio = $eponCfg['802-1pMark']['_value'] ?? null;

                // PPPConnections
                $pppConns = $cd['WANPPPConnection'] ?? [];
                foreach ($pppConns as $pppIdx => $ppp) {
                    if (! is_numeric($pppIdx)) {
                        continue;
                    }
                    $get = fn (string $key) => $ppp[$key]['_value'] ?? null;

                    $connections[] = [
                        'wan_idx' => (int) $wdIdx,
                        'cd_idx' => (int) $cdIdx,
                        'ppp_idx' => (int) $pppIdx,
                        'key' => "{$wdIdx}.{$cdIdx}.{$pppIdx}",
                        'name' => $get('Name'),
                        'enabled' => $get('Enable'),
                        'status' => $get('ConnectionStatus'),
                        'connection_type' => $get('ConnectionType'),
                        'transport_type' => $get('TransportType'),
                        'username' => $get('Username'),
                        'external_ip' => $get('ExternalIPAddress'),
                        'remote_ip' => $get('RemoteIPAddress'),
                        'uptime' => $get('Uptime'),
                        'nat_enabled' => $get('NATEnabled'),
                        'dns_servers' => $get('DNSServers'),
                        'service_list' => $get('X_CT-COM_ServiceList'),
                        'lan_interface' => $get('X_CT-COM_LanInterface'),
                        'vlan_id' => $vlan,
                        'vlan_prio' => $prio,
                        'vlan_mode' => $mode,
                        'mac_address' => $get('MACAddress'),
                        'path_prefix' => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.WANPPPConnection.{$pppIdx}",
                        'vlan_path_prefix' => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.X_CT-COM_WANEponLinkConfig",
                    ];
                }

                // IPConnections
                $ipConns = $cd['WANIPConnection'] ?? [];
                foreach ($ipConns as $ipIdx => $ip) {
                    if (! is_numeric($ipIdx)) {
                        continue;
                    }
                    $get = fn (string $key) => $ip[$key]['_value'] ?? null;

                    $connections[] = [
                        'wan_idx' => (int) $wdIdx,
                        'cd_idx' => (int) $cdIdx,
                        'ppp_idx' => null,
                        'key' => "{$wdIdx}.{$cdIdx}.ip{$ipIdx}",
                        'name' => $get('Name'),
                        'enabled' => $get('Enable'),
                        'status' => $get('ConnectionStatus'),
                        'connection_type' => $get('ConnectionType'),
                        'transport_type' => 'IP',
                        'username' => null,
                        'external_ip' => $get('ExternalIPAddress'),
                        'remote_ip' => null,
                        'uptime' => $get('Uptime'),
                        'nat_enabled' => $get('NATEnabled'),
                        'dns_servers' => $get('DNSServers'),
                        'service_list' => $get('X_CT-COM_ServiceList'),
                        'lan_interface' => $get('X_CT-COM_LanInterface'),
                        'vlan_id' => $vlan,
                        'vlan_prio' => $prio,
                        'vlan_mode' => $mode,
                        'path_prefix' => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.WANIPConnection.{$ipIdx}",
                        'vlan_path_prefix' => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.X_CT-COM_WANEponLinkConfig",
                    ];
                }
            }
        }

        return $connections;
    }

    /**
     * Delete a device from GenieACS by its device ID.
     * DELETE /devices/{deviceId}
     */
    public function deleteDevice(string $deviceId): bool
    {
        $response = $this->request()->delete('/devices/'.rawurlencode($deviceId));

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Delete a pending task by its ID.
     * DELETE /tasks/{taskId}
     */
    public function deleteTask(string $taskId): bool
    {
        $response = $this->request()->delete('/tasks/'.rawurlencode($taskId));

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Delete ALL pending tasks for a specific device.
     */
    public function deleteDeviceTasks(string $deviceId): int
    {
        $query = json_encode(['device' => $deviceId]);
        $response = $this->get('/tasks/', ['query' => $query]);

        if (! $response->successful()) {
            return 0;
        }

        $count = 0;
        foreach ($response->json() ?? [] as $task) {
            if (isset($task['_id']) && $this->deleteTask($task['_id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send a connection request to a CPE device to force an immediate Inform.
     * Uses a reboot task body with connection_request flag but timeout=0 so it
     * only triggers the connection request without queuing a real reboot.
     * Actually uses getParameterValues on a universal parameter supported by both
     * TR-098 and TR-181 devices.
     */
    public function sendConnectionRequest(string $deviceId, string $profile = 'igd'): bool
    {
        // Use connection_request flag with timeout=0 — this tells GenieACS to send
        // a connection request to wake the device WITHOUT waiting for task execution.
        $paramName = $profile === 'device'
            ? 'Device.DeviceInfo.UpTime'
            : 'InternetGatewayDevice.DeviceInfo.UpTime';

        $response = $this->post(
            '/devices/'.rawurlencode($deviceId).'/tasks?connection_request&timeout=3000',
            ['name' => 'getParameterValues', 'parameterNames' => [$paramName]],
            10
        );

        // 200 = executed immediately (device woke up), 202 = queued (device unreachable)
        // We delete 202 tasks immediately since we only wanted the wake-up ping.
        if ($response->status() === 202) {
            $body = $response->json() ?? [];
            $taskId = $body['_id'] ?? null;
            if ($taskId) {
                $this->deleteTask($taskId);
            }

            return false; // device not reachable
        }

        return $response->status() === 200;
    }

    /**
     * Check if GenieACS NBI is reachable.
     */
    public function getStatus(): array
    {
        try {
            $response = $this->get('/devices/', ['limit' => 1]);

            return ['online' => $response->successful(), 'nbi_url' => $this->baseUrl];
        } catch (\Throwable) {
            return ['online' => false, 'nbi_url' => $this->baseUrl];
        }
    }

    // -------------------------------------------------------------------------
    // Private HTTP helpers
    // -------------------------------------------------------------------------

    private function request(): PendingRequest
    {
        $req = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->username !== '') {
            $req = $req->withBasicAuth($this->username, $this->password);
        }

        return $req;
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->request()->get($path, $query);
    }

    private function post(string $path, array $body = [], ?int $timeout = null): Response
    {
        $req = $this->request();
        if ($timeout !== null) {
            $req = $req->timeout($timeout);
        }

        return $req->post($path, $body);
    }

    private function put(string $path, array $body = []): Response
    {
        return $this->request()->put($path, $body);
    }
}
