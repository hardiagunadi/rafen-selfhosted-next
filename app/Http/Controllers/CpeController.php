<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCpePppoeRequest;
use App\Http\Requests\UpdateCpeWifiRequest;
use App\Models\CpeDevice;
use App\Models\OltOnuOptic;
use App\Models\OltOnuOpticHistory;
use App\Models\PppUser;
use App\Models\RadiusAccount;
use App\Models\TenantSettings;
use App\Services\GenieAcsClient;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CpeController extends Controller
{
    use LogsActivity;

    private ?GenieAcsClient $genieacs;

    public function __construct(?GenieAcsClient $genieacs = null)
    {
        // Lazy: jika $genieacs tidak diinjeksi, akan dibuat saat pertama dibutuhkan via genieacs()
        $this->genieacs = $genieacs;
    }

    /**
     * Resolve GenieACS client untuk tenant saat ini.
     * Abort 403 jika tenant non-super-admin belum mengkonfigurasi GenieACS,
     * sehingga tidak ada kebocoran ke GenieACS milik tenant lain via fallback .env.
     */
    private function genieacs(): GenieAcsClient
    {
        if ($this->genieacs === null) {
            $this->genieacs = $this->makeGenieacsClient();
        }

        return $this->genieacs;
    }

    private function makeGenieacsClient(): GenieAcsClient
    {
        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin()) {
            $ownerId  = $user->effectiveOwnerId();
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasGenieacsConfigured()) {
                return GenieAcsClient::fromTenantSettings($settings);
            }
            // Tenant tidak punya GenieACS configured — jangan fallback ke .env global
            // karena itu bisa mengakses GenieACS milik tenant lain
            abort(403, 'GenieACS belum dikonfigurasi untuk tenant ini.');
        }

        return new GenieAcsClient();
    }

    /**
     * Global CPE list for the tenant.
     */
    public function index(): View
    {
        $user    = auth()->user();
        $devices = CpeDevice::query()
            ->accessibleBy($user)
            ->with('pppUser:id,customer_name,username')
            ->latest('last_seen_at')
            ->paginate(50);

        return view('cpe.index', compact('devices'));
    }

    /**
     * DataTable JSON for CPE index.
     */
    public function datatable(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $query = CpeDevice::query()
            ->accessibleBy($user)
            ->with('pppUser:id,customer_name,username');

        $search = $request->input('search.value');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('manufacturer', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('pppUser', fn ($q) => $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $total    = $query->count();
        $devices  = $query->orderBy('last_seen_at', 'desc')
            ->offset($request->input('start', 0))
            ->limit($request->input('length', 25))
            ->get();

        $data = $devices->map(fn (CpeDevice $d) => [
            'id'              => $d->id,
            'customer_name'   => $d->pppUser?->customer_name ?? '-',
            'username'        => $d->pppUser?->username ?? '-',
            'ppp_user_id'     => $d->ppp_user_id,
            'manufacturer'    => $d->manufacturer ?? '-',
            'model'           => $d->model ?? '-',
            'firmware'        => $d->firmware_version ?? '-',
            'inform_interval' => $d->cached_params['inform_interval'] ?? null,
            'status'          => $d->status ?? 'unknown',
            'last_seen_at'    => $d->last_seen_at?->diffForHumans() ?? '-',
        ]);

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    /**
     * Show CPE panel for a specific PppUser.
     */
    public function show(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $pppUser->cpeDevice;

        if (! $device) {
            return response()->json(['linked' => false]);
        }

        return response()->json([
            'linked'        => true,
            'device'        => $this->deviceToArray($device),
        ]);
    }

    /**
     * Sync: find device in GenieACS by PPPoE username and save/update local record.
     */
    public function sync(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        try {
            $genieDevice = $this->genieacs()->findDeviceByUsername($pppUser->username);

            if (! $genieDevice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perangkat tidak ditemukan di GenieACS. Pastikan CPE sudah terhubung ke ACS.',
                ], 404);
            }

            $device = $pppUser->cpeDevice ?? new CpeDevice([
                'ppp_user_id' => $pppUser->id,
                'owner_id'    => $pppUser->owner_id,
            ]);

            $device->updateFromGenieacs($genieDevice);
            $device->save();

            // Trigger GenieACS to fetch full parameter tree from CPE
            // so WiFi SSID etc. are populated on next inform
            $rootObj = isset($genieDevice['InternetGatewayDevice'])
                ? 'InternetGatewayDevice'
                : 'Device';
            try {
                $this->genieacs()->refreshObject($device->genieacs_device_id, $rootObj);
            } catch (Throwable) {
                // Non-fatal — device info saved, refresh is best-effort
            }

        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perangkat berhasil dihubungkan. Parameter WiFi akan tersedia setelah modem kontak ke ACS berikutnya.',
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Fast refresh: fetch latest cached data from GenieACS only (no connection_request).
     * Used for auto-load when CPE tab is opened.
     */
    public function refreshFromCache(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        try {
            $genieDevice = $this->genieacs()->getDeviceInfo($device->genieacs_device_id);
            if (! empty($genieDevice)) {
                $device->updateFromGenieacs($genieDevice);
                $device->save();
            }
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Refresh device info from GenieACS (also issues refreshObject task).
     */
    public function refreshParams(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        // Always fetch latest cached data from GenieACS first
        try {
            $genieDevice = $this->genieacs()->getDeviceInfo($device->genieacs_device_id);
            if (! empty($genieDevice)) {
                $device->updateFromGenieacs($genieDevice);
                $device->save();
            }
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Fetch WLAN + WAN params via connection_request (modem langsung dikontaknya),
        // lalu fetch ulang data dari GenieACS setelah task selesai.
        try {
            $profile    = $device->param_profile ?? 'igd';
            $paramNames = [];

            if ($profile === 'igd') {
                // TR-098: WLAN dari LANDevice.1.WLANConfiguration
                $wlanIndices = array_column($device->cached_params['wifi_networks'] ?? [], 'index') ?: [1, 2, 3, 4];
                foreach ($wlanIndices as $idx) {
                    $base = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}";
                    foreach (['Enable', 'SSID', 'KeyPassphrase', 'Channel', 'Standard', 'X_CT-COM_RFBand'] as $field) {
                        $paramNames[] = "{$base}.{$field}";
                    }
                }

                // TR-098: WAN dari WANDevice tree
                $wanDevices = $genieDevice['InternetGatewayDevice']['WANDevice'] ?? [];
                foreach ($wanDevices as $wdIdx => $wanDev) {
                    if (! is_numeric($wdIdx)) {
                        continue;
                    }
                    $wanCds = $wanDev['WANConnectionDevice'] ?? [];
                    foreach ($wanCds as $cdIdx => $cd) {
                        if (! is_numeric($cdIdx)) {
                            continue;
                        }
                        foreach (array_keys($cd['WANPPPConnection'] ?? []) as $pppIdx) {
                            if (! is_numeric($pppIdx)) {
                                continue;
                            }
                            $base = "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.WANPPPConnection.{$pppIdx}";
                            foreach (['ConnectionStatus', 'ConnectionType', 'Name', 'MACAddress', 'Enable', 'NATEnabled', 'DNSServers', 'Uptime', 'X_CT-COM_ServiceList'] as $field) {
                                $paramNames[] = "{$base}.{$field}";
                            }
                        }
                        foreach (array_keys($cd['WANIPConnection'] ?? []) as $ipIdx) {
                            if (! is_numeric($ipIdx)) {
                                continue;
                            }
                            $base = "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.WANIPConnection.{$ipIdx}";
                            foreach (['ConnectionStatus', 'ConnectionType', 'Name', 'MACAddress', 'Enable', 'NATEnabled', 'DNSServers', 'Uptime'] as $field) {
                                $paramNames[] = "{$base}.{$field}";
                            }
                        }
                    }
                }
            } else {
                // TR-181: PPP Interface
                $pppIfaces = $genieDevice['Device']['PPP']['Interface'] ?? [];
                foreach ($pppIfaces as $idx => $_) {
                    if (! is_numeric($idx)) {
                        continue;
                    }
                    $base = "Device.PPP.Interface.{$idx}";
                    foreach (['Username', 'ConnectionStatus', 'Enable', 'Name', 'Uptime', 'MACAddress'] as $field) {
                        $paramNames[] = "{$base}.{$field}";
                    }
                    $paramNames[] = "{$base}.IPCP.LocalIPAddress";
                }
                // Fallback jika tree belum ada di cache
                if (empty($pppIfaces)) {
                    foreach (['Username', 'ConnectionStatus', 'Enable', 'Name', 'Uptime'] as $field) {
                        $paramNames[] = "Device.PPP.Interface.1.{$field}";
                    }
                }

                // TR-181: WiFi SSID + AccessPoint
                $ssids = $genieDevice['Device']['WiFi']['SSID'] ?? [];
                foreach ($ssids as $idx => $_) {
                    if (! is_numeric($idx)) {
                        continue;
                    }
                    foreach (['SSID', 'Enable', 'LowerLayers'] as $field) {
                        $paramNames[] = "Device.WiFi.SSID.{$idx}.{$field}";
                    }
                    $paramNames[] = "Device.WiFi.AccessPoint.{$idx}.Security.KeyPassphrase";
                    $paramNames[] = "Device.WiFi.AccessPoint.{$idx}.Security.ModeEnabled";
                }
                // Fallback SSID index 1 & 2
                if (empty($ssids)) {
                    foreach ([1, 2] as $idx) {
                        $paramNames[] = "Device.WiFi.SSID.{$idx}.SSID";
                        $paramNames[] = "Device.WiFi.SSID.{$idx}.Enable";
                        $paramNames[] = "Device.WiFi.AccessPoint.{$idx}.Security.KeyPassphrase";
                    }
                }

                // TR-181: Radio untuk channel/band info
                foreach ([1, 2] as $idx) {
                    $paramNames[] = "Device.WiFi.Radio.{$idx}.Channel";
                    $paramNames[] = "Device.WiFi.Radio.{$idx}.OperatingFrequencyBand";
                    $paramNames[] = "Device.WiFi.Radio.{$idx}.OperatingStandards";
                }
            }

            // connection_request=true → GenieACS langsung kontak modem, tunggu sampai selesai
            $this->genieacs()->createTask($device->genieacs_device_id, [
                'name'           => 'getParameterValues',
                'parameterNames' => $paramNames,
            ], true);

            // Fetch ulang dari GenieACS setelah task selesai sehingga SSID dll terupdate
            $fresh = $this->genieacs()->getDeviceInfo($device->genieacs_device_id);
            if (! empty($fresh)) {
                $device->updateFromGenieacs($fresh);
                $device->save();
            }
        } catch (Throwable) {
            // Non-fatal — tetap kembalikan data terkini yang ada
        }

        return response()->json([
            'success' => true,
            'message' => 'Info perangkat diperbarui.',
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Get real-time PPPoE traffic from Simple Queue by username.
     */
    public function getTraffic(int $pppUserId): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $pppUser     = $this->findPppUser($pppUserId);
        $user        = auth()->user();
        $connections = \App\Models\MikrotikConnection::query()
            ->accessibleBy($user)
            ->get();

        // MikroTik PPPoE dynamic queue name format: <pppoe-{username}>
        $queueName = '<pppoe-'.$pppUser->username.'>';

        foreach ($connections as $connection) {
            try {
                $client = new \App\Services\MikrotikApiClient($connection);
                $result = $client->command('/queue/simple/print', [], [
                    'name' => $queueName,
                ]);
                $client->disconnect();

                $queue = $result['data'][0] ?? null;
                if (! $queue) {
                    continue;
                }

                // bytes = "bytes_in/bytes_out", rate = "rx-rate/tx-rate" in bps
                [$bytesIn, $bytesOut] = array_pad(explode('/', $queue['bytes'] ?? '0/0'), 2, '0');
                [$rxRate, $txRate]    = array_pad(explode('/', $queue['rate'] ?? '0/0'), 2, '0');

                return response()->json([
                    'is_active'  => true,
                    'tx'         => (int) $txRate,
                    'rx'         => (int) $rxRate,
                    'bytes_in'   => (int) $bytesIn,
                    'bytes_out'  => (int) $bytesOut,
                    'queue_name' => $queue['name'] ?? $queueName,
                ]);
            } catch (\RuntimeException) {
                continue;
            }
        }

        return response()->json(['is_active' => false]);
    }

    /**
     * Get fresh device info (AJAX).
     */
    public function getInfo(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        return response()->json([
            'success' => true,
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Reboot the CPE device.
     */
    public function reboot(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canRebootCpe()) {
            abort(403);
        }

        try {
            $result = $this->genieacs()->rebootDevice($device->genieacs_device_id);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengirim perintah reboot: '.$e->getMessage()], 500);
        }

        $this->logActivity('reboot_cpe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $lastSeen = $device->last_seen_at;
        $msg = $result['queued']
            ? 'Perintah reboot dikirim. Perangkat tidak dapat dihubungi saat ini — akan restart saat sesi TR-069 berikutnya.'
              .($lastSeen ? ' (Terakhir terhubung: '.$lastSeen->diffForHumans().')' : '')
            : 'Perintah reboot berhasil dikirim. Perangkat sedang restart.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Update WiFi SSID and password.
     */
    public function updateWifi(UpdateCpeWifiRequest $request, int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        try {
            $result = $this->genieacs()->setWifi(
                $device->genieacs_device_id,
                $request->validated('ssid'),
                $request->validated('password'),
                $device->param_profile ?? 'igd'
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Update cached SSID
        $cached             = $device->cached_params ?? [];
        $cached['wifi_ssid'] = $request->validated('ssid');
        $device->cached_params = $cached;
        $device->save();

        $this->logActivity('update_wifi', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Konfigurasi WiFi dikirim. Akan diterapkan saat perangkat online.'
            : 'Konfigurasi WiFi berhasil diterapkan.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Update PPPoE credentials on the CPE.
     */
    public function updatePppoe(UpdateCpePppoeRequest $request, int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        try {
            $result = $this->genieacs()->setPppoeCredentials(
                $device->genieacs_device_id,
                $request->validated('username'),
                $request->validated('password'),
                $device->param_profile ?? 'igd'
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $this->logActivity('update_pppoe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Kredensial PPPoE dikirim. Akan diterapkan saat perangkat online.'
            : 'Kredensial PPPoE berhasil diterapkan.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Manually update MAC address of the linked CPE device.
     */
    public function updateMac(Request $request, int $pppUserId): JsonResponse
    {
        $request->validate([
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
        ]);

        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        $mac = strtolower(str_replace('-', ':', $request->input('mac_address')));
        $device->mac_address = $mac;
        $device->save();

        $this->logActivity('update_mac', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        return response()->json(['success' => true, 'message' => 'MAC address berhasil diperbarui.']);
    }

    /**
     * Update WiFi SSID/password/enabled for a specific WLANConfiguration index.
     */
    public function updateWifiByIndex(Request $request, int $pppUserId, int $wlanIdx): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canWifiCpe()) {
            abort(403);
        }

        $isTeknisi = auth()->user()->role === 'teknisi';

        $validated = $request->validate([
            'ssid'     => 'nullable|string|max:32',
            'password' => 'nullable|string|min:8|max:63',
            'enabled'  => $isTeknisi ? 'prohibited' : 'nullable|boolean',
            'channel'  => $isTeknisi ? 'prohibited' : 'nullable|integer|min:0|max:165',
        ]);

        $profile = $device->param_profile ?? 'igd';
        $params  = [];

        if ($profile === 'igd') {
            $base = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIdx}";
            if (isset($validated['ssid']))     $params[] = ["{$base}.SSID",           $validated['ssid'],                          'xsd:string'];
            if (isset($validated['password'])) $params[] = ["{$base}.KeyPassphrase",  $validated['password'],                      'xsd:string'];
            if (isset($validated['enabled']))  $params[] = ["{$base}.Enable",         $validated['enabled'] ? 'true' : 'false',    'xsd:boolean'];
            if (isset($validated['channel']))  $params[] = ["{$base}.Channel",        (string) $validated['channel'],              'xsd:unsignedInt'];
        } else {
            // TR-181: SSID fields di Device.WiFi.SSID.*, password di Device.WiFi.AccessPoint.*
            if (isset($validated['ssid']))     $params[] = ["Device.WiFi.SSID.{$wlanIdx}.SSID",                              $validated['ssid'],                       'xsd:string'];
            if (isset($validated['password'])) $params[] = ["Device.WiFi.AccessPoint.{$wlanIdx}.Security.KeyPassphrase",     $validated['password'],                   'xsd:string'];
            if (isset($validated['enabled']))  $params[] = ["Device.WiFi.SSID.{$wlanIdx}.Enable",                           $validated['enabled'] ? 'true' : 'false', 'xsd:boolean'];
            // TR-181 channel diatur via Radio object (tidak per-SSID), skip channel untuk TR-181
        }

        if (empty($params)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada perubahan.'], 422);
        }

        try {
            $result = $this->genieacs()->setParameterValues($device->genieacs_device_id, $params);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Update cache
        $cached  = $device->cached_params ?? [];
        $networks = $cached['wifi_networks'] ?? [];
        foreach ($networks as &$net) {
            if ($net['index'] === $wlanIdx) {
                if (isset($validated['ssid']))     $net['ssid']    = $validated['ssid'];
                if (isset($validated['password']))  $net['password'] = $validated['password'];
                if (isset($validated['enabled']))   $net['enabled']  = $validated['enabled'];
                if (isset($validated['channel']))   $net['channel']  = $validated['channel'];
                break;
            }
        }
        unset($net);
        $cached['wifi_networks']   = $networks;
        $device->cached_params     = $cached;
        $device->save();

        $this->logActivity('update_wifi', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? "Konfigurasi WiFi #{$wlanIdx} dikirim. Akan diterapkan saat perangkat online."
            : "Konfigurasi WiFi #{$wlanIdx} berhasil diterapkan.";

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Get current WAN connections (from cache).
     */
    public function getWanConnections(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        $connections = $device->cached_params['wan_connections'] ?? [];

        return response()->json(['success' => true, 'wan_connections' => $connections]);
    }

    /**
     * Update a WAN connection parameters.
     */
    public function updateWanConnection(Request $request, int $pppUserId, int $wanIdx, int $cdIdx, string $connIdx): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        $validated = $request->validate([
            'enabled'         => 'nullable|boolean',
            'username'        => 'nullable|string|max:64',
            'password'        => 'nullable|string|max:64',
            'nat_enabled'     => 'nullable|boolean',
            'dns_servers'     => 'nullable|string|max:128',
            'connection_type' => 'nullable|in:IP_Routed,PPPoE_Bridged',
            'vlan_id'         => 'nullable|integer|min:1|max:4094',
            'vlan_prio'       => 'nullable|integer|min:0|max:7',
            'lan_interface'   => 'nullable|string|max:512',
        ]);

        $profile = $device->param_profile ?? 'igd';
        $params  = [];

        if ($profile === 'igd') {
            // TR-098: InternetGatewayDevice.WANDevice.* paths
            $connType = str_starts_with($connIdx, 'ip') ? 'WANIPConnection' : 'WANPPPConnection';
            $connNum  = str_starts_with($connIdx, 'ip') ? ltrim($connIdx, 'ip') : $connIdx;
            $base     = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.{$cdIdx}.{$connType}.{$connNum}";
            $vlanBase = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.{$cdIdx}.X_CT-COM_WANEponLinkConfig";

            if (isset($validated['enabled']))         $params[] = ["{$base}.Enable",                       $validated['enabled'] ? 'true' : 'false',        'xsd:boolean'];
            if (isset($validated['username']))         $params[] = ["{$base}.Username",                     $validated['username'],                          'xsd:string'];
            if (isset($validated['password']))         $params[] = ["{$base}.Password",                     $validated['password'],                          'xsd:string'];
            if (isset($validated['nat_enabled']))      $params[] = ["{$base}.NATEnabled",                   $validated['nat_enabled'] ? 'true' : 'false',    'xsd:boolean'];
            if (isset($validated['dns_servers']))      $params[] = ["{$base}.DNSServers",                   $validated['dns_servers'],                       'xsd:string'];
            if (isset($validated['connection_type']))  $params[] = ["{$base}.ConnectionType",               $validated['connection_type'],                   'xsd:string'];
            if (isset($validated['lan_interface']))    $params[] = ["{$base}.X_CT-COM_LanInterface",        $validated['lan_interface'],                     'xsd:string'];
            if (isset($validated['vlan_id']))          $params[] = ["{$vlanBase}.VLANIDMark",               (string) $validated['vlan_id'],                  'xsd:unsignedInt'];
            if (isset($validated['vlan_prio']))        $params[] = ["{$vlanBase}.802-1pMark",               (string) $validated['vlan_prio'],                'xsd:unsignedInt'];
        } else {
            // TR-181: Device.PPP.Interface.{idx} — hanya username/password/enable yang bisa diset
            // VLAN, LAN interface, dan connection_type tidak tersedia via TR-181 standar
            $base = "Device.PPP.Interface.{$wanIdx}";
            if (isset($validated['enabled']))  $params[] = ["{$base}.Enable",   $validated['enabled'] ? 'true' : 'false', 'xsd:boolean'];
            if (isset($validated['username'])) $params[] = ["{$base}.Username", $validated['username'],                   'xsd:string'];
            if (isset($validated['password'])) $params[] = ["{$base}.Password", $validated['password'],                   'xsd:string'];
        }

        if (empty($params)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada perubahan.'], 422);
        }

        try {
            $result = $this->genieacs()->setParameterValues($device->genieacs_device_id, $params);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Update cache
        $cached      = $device->cached_params ?? [];
        $connections = $cached['wan_connections'] ?? [];
        $key         = "{$wanIdx}.{$cdIdx}.{$connIdx}";
        foreach ($connections as &$conn) {
            if ($conn['key'] === $key) {
                foreach (['enabled','username','nat_enabled','dns_servers','connection_type','vlan_id','vlan_prio','lan_interface'] as $f) {
                    if (isset($validated[$f])) $conn[$f === 'lan_interface' ? 'lan_interface' : $f] = $validated[$f];
                }
                break;
            }
        }
        unset($conn);
        $cached['wan_connections'] = $connections;
        $device->cached_params     = $cached;
        $device->save();

        $this->logActivity('update_wan', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Konfigurasi WAN dikirim. Akan diterapkan saat perangkat online.'
            : 'Konfigurasi WAN berhasil diterapkan.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Remove the CPE device link (local record only, not GenieACS).
     */
    public function destroy(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        $device = $pppUser->cpeDevice;
        if ($device) {
            $this->logActivity('unlink_cpe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);
            $device->delete();
        }

        return response()->json(['success' => true, 'message' => 'Perangkat berhasil dilepaskan.']);
    }

    /**
     * Search PPP users for Select2 (link modal).
     */
    public function searchPppUsers(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $term   = $request->input('q', '');
        $users  = PppUser::query()
            ->accessibleBy($user)
            ->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', "%{$term}%")
                  ->orWhere('username', 'like', "%{$term}%");
            })
            ->limit(20)
            ->get(['id', 'customer_name', 'username', 'ppp_password']);

        return response()->json([
            'results' => $users->map(fn ($u) => [
                'id'       => $u->id,
                'text'     => $u->customer_name.' ('.$u->username.')',
                'username' => $u->username,
                'password' => $u->ppp_password,
            ]),
        ]);
    }

    /**
     * List GenieACS devices that are not yet linked to any PPP user.
     */
    public function unlinkedDevices(): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        // Get ALL linked genieacs_device_ids across all tenants — a device linked to any tenant must not appear as unlinked
        $user   = auth()->user();
        $linked = CpeDevice::query()->pluck('genieacs_device_id')->all();

        // Fetch all devices from GenieACS
        $all = $this->genieacs()->listDevices();

        $unlinked = [];
        foreach ($all as $dev) {
            $id = $dev['_id'] ?? null;
            if (! $id || in_array($id, $linked, true)) {
                continue;
            }

            // Skip DISCOVERYSERVICE pseudo-devices created by GenieACS internally
            if (str_starts_with($id, 'DISCOVERYSERVICE-')) {
                continue;
            }

            $profile   = $this->genieacs()->detectParamProfile($dev);
            $pppoeUser = $this->genieacs()->getParamValue($dev, 'pppoe_username');

            // If PPPoE username not yet fetched, queue getParameterValues (fire-and-forget, no CR).
            // Rate-limited per device (10 menit) agar tidak spam task saat user refresh berkali-kali.
            if (! $pppoeUser) {
                $pppoeParam = config("genieacs.params.{$profile}.pppoe_username");
                $cacheKey   = 'cpe_fetch_task_'.md5($id);
                if ($pppoeParam && ! \Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    try {
                        $this->genieacs()->createTask($id, [
                            'name'           => 'getParameterValues',
                            'parameterNames' => [$pppoeParam],
                        ], false);
                        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addMinutes(10));
                    } catch (\Throwable) {
                        // Non-fatal
                    }
                }
            }

            $informInterval = $this->genieacs()->extractValue($dev,
                $profile === 'igd'
                    ? 'InternetGatewayDevice.ManagementServer.PeriodicInformInterval'
                    : 'Device.ManagementServer.PeriodicInformInterval'
            );

            // Extract MAC address dari beberapa path yang umum
            $mac = $this->genieacs()->extractValue($dev, 'InternetGatewayDevice.DeviceInfo.X_CU_SerialNumber')
                ?? $this->genieacs()->extractValue($dev, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.MACAddress')
                ?? $this->genieacs()->extractValue($dev, 'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1.MACAddress')
                ?? $this->genieacs()->extractValue($dev, 'Device.Ethernet.Interface.1.MACAddress')
                ?? null;

            $unlinked[] = [
                'genieacs_id'     => $id,
                'manufacturer'    => $this->genieacs()->getParamValue($dev, 'manufacturer') ?? '-',
                'model'           => $this->genieacs()->getParamValue($dev, 'model') ?? '-',
                'serial'          => $this->genieacs()->getParamValue($dev, 'serial_number') ?? '-',
                'firmware'        => $this->genieacs()->getParamValue($dev, 'firmware_version') ?? '-',
                'mac_address'     => $mac,
                'pppoe_user'      => $pppoeUser ?? '-',
                'inform_interval' => $informInterval ? (int) $informInterval : null,
                'last_inform'     => isset($dev['_lastInform'])
                    ? \Carbon\Carbon::parse($dev['_lastInform'])->diffForHumans()
                    : '-',
            ];
        }

        return response()->json($unlinked);
    }

    /**
     * Trigger refreshObject untuk device di Belum Terhubung agar GenieACS mengambil PPPoE username.
     */
    public function refreshUnlinkedParam(string $genieacsId): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $deviceId = rawurldecode($genieacsId);
        $dev      = $this->genieacs()->getDeviceInfo($deviceId);

        if (empty($dev)) {
            return response()->json(['success' => false, 'message' => 'Device tidak ditemukan di GenieACS.'], 404);
        }

        $profile = $this->genieacs()->detectParamProfile($dev);
        $wanPath = config("genieacs.params.{$profile}.wan_object");

        if (! $wanPath) {
            return response()->json(['success' => false, 'message' => 'Konfigurasi wan_object tidak ditemukan.'], 422);
        }

        try {
            $this->genieacs()->refreshObject($deviceId, $wanPath);

            return response()->json(['success' => true, 'message' => 'Perintah refresh berhasil dikirim. Parameter PPPoE akan muncul setelah modem merespons.']);
        } catch (\Throwable $e) {
            Log::warning('CpeController::refreshUnlinkedParam failed', [
                'deviceId' => $deviceId,
                'error'    => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Gagal mengirim perintah refresh: '.$e->getMessage()], 500);
        }
    }

    /**
     * Delete an unlinked device from GenieACS.
     */
    public function deleteUnlinkedDevice(string $genieacsId): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $user = auth()->user();

        // Ensure the device is truly unlinked (not owned by this or any tenant)
        $linked = CpeDevice::query()->accessibleBy($user)->where('genieacs_device_id', $genieacsId)->exists();
        if ($linked) {
            return response()->json(['success' => false, 'message' => 'Device ini sudah terhubung ke PPP user, tidak bisa dihapus dari sini.'], 422);
        }

        $deleted = $this->genieacs()->deleteDevice($genieacsId);
        if (! $deleted) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus device dari GenieACS.'], 500);
        }

        $this->logActivity('delete_unlinked_cpe', 'CpeDevice', null, $genieacsId, $user->effectiveOwnerId());

        return response()->json(['success' => true, 'message' => 'Device berhasil dihapus dari GenieACS.']);
    }

    /**
     * Bulk auto-link unlinked GenieACS devices to PPP users.
     * Devices with detected PPPoE username → link immediately.
     * Devices without PPPoE username → queue refreshObject to fetch params.
     */
    public function bulkAutoLink(): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $user   = auth()->user();
        // Exclude device yang sudah linked ke tenant MANAPUN agar tidak muncul sebagai "Belum Terhubung"
        $linked = CpeDevice::query()->pluck('genieacs_device_id')->all();
        $all    = $this->genieacs()->listDevices();

        $linkedCount  = 0;
        $refreshCount = 0;

        foreach ($all as $dev) {
            $id = $dev['_id'] ?? null;
            if (! $id || in_array($id, $linked, true) || str_starts_with($id, 'DISCOVERYSERVICE-')) {
                continue;
            }

            $pppoeUser = $this->genieacs()->getParamValue($dev, 'pppoe_username');

            if ($pppoeUser) {
                $pppUser = PppUser::query()
                    ->accessibleBy($user)
                    ->whereRaw('LOWER(username) = ?', [strtolower($pppoeUser)])
                    ->whereDoesntHave('cpeDevice')
                    ->first();

                if ($pppUser) {
                    $cpe = new CpeDevice([
                        'ppp_user_id' => $pppUser->id,
                        'owner_id'    => $pppUser->owner_id,
                    ]);
                    $cpe->updateFromGenieacs($dev);
                    $cpe->save();

                    try {
                        $rootObj = isset($dev['InternetGatewayDevice']) ? 'InternetGatewayDevice' : 'Device';
                        $this->genieacs()->refreshObject($id, $rootObj);
                    } catch (Throwable) {
                        // Non-fatal
                    }

                    $linked[] = $id;
                    $linkedCount++;
                    Log::info("CpeController::bulkAutoLink linked {$pppoeUser} → {$id}");
                }
            } else {
                $profile = $this->genieacs()->detectParamProfile($dev);
                $wanPath = config("genieacs.params.{$profile}.wan_object");
                if ($wanPath) {
                    try {
                        $this->genieacs()->refreshObject($id, $wanPath);
                        $refreshCount++;
                    } catch (Throwable) {
                        // Non-fatal
                    }
                }
            }
        }

        $parts = [];
        if ($linkedCount > 0) {
            $parts[] = "{$linkedCount} perangkat berhasil dihubungkan";
        }
        if ($refreshCount > 0) {
            $parts[] = "{$refreshCount} perangkat dikirim perintah refresh (tunggu 30–60 detik lalu klik Refresh)";
        }
        if (empty($parts)) {
            $message = 'Tidak ada perangkat yang bisa dihubungkan saat ini.';
        } else {
            $message = implode(', ', $parts) . '.';
        }

        return response()->json([
            'success'       => true,
            'message'       => $message,
            'linked'        => $linkedCount,
            'refresh_queued' => $refreshCount,
        ]);
    }

    /**
     * Manually link a GenieACS device ID to a PPP user.
     */
    public function linkDevice(Request $request): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $validated = $request->validate([
            'genieacs_id' => 'required|string|max:255',
            'ppp_user_id' => 'required|integer',
        ]);

        $pppUser = $this->findPppUser($validated['ppp_user_id']);

        // Fetch device from GenieACS
        $genieDevice = $this->genieacs()->getDeviceInfo($validated['genieacs_id']);
        if (empty($genieDevice)) {
            return response()->json(['success' => false, 'message' => 'Device tidak ditemukan di GenieACS.'], 404);
        }

        // Remove existing link on this PPP user if any
        $pppUser->cpeDevice?->delete();

        $device = new CpeDevice([
            'ppp_user_id' => $pppUser->id,
            'owner_id'    => $pppUser->owner_id,
        ]);
        $device->updateFromGenieacs($genieDevice);
        $device->save();

        try {
            $rootObj = isset($genieDevice['InternetGatewayDevice']) ? 'InternetGatewayDevice' : 'Device';
            $this->genieacs()->refreshObject($device->genieacs_device_id, $rootObj);
        } catch (Throwable) {
            // non-fatal
        }

        $this->logActivity('link_cpe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        return response()->json(['success' => true, 'message' => 'Perangkat berhasil dihubungkan ke '.$pppUser->customer_name.'.']);
    }

    /**
     * Return device info for an unlinked GenieACS device (for modal confirmation before set PPPoE).
     */
    public function showUnlinkedDeviceInfo(string $genieacsId): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $deviceId = rawurldecode($genieacsId);
        $dev      = $this->genieacs()->getDeviceInfo($deviceId);

        if (empty($dev)) {
            return response()->json(['success' => false, 'message' => 'Device tidak ditemukan di GenieACS.'], 404);
        }

        $profile = $this->genieacs()->detectParamProfile($dev);

        // Extract current PPPoE username & connection status
        $wanConns   = $this->genieacs()->extractWanConnections($dev);
        $pppConn    = collect($wanConns)->firstWhere('connection_type', 'PPPoE') ?? collect($wanConns)->first();
        $pppoeUser  = $pppConn['username'] ?? null;
        $connStatus = $pppConn['status'] ?? null;
        $externalIp = $pppConn['external_ip'] ?? null;

        return response()->json([
            'success'       => true,
            'genieacs_id'   => $genieacsId,
            'manufacturer'  => $this->genieacs()->getParamValue($dev, 'manufacturer') ?? '-',
            'model'         => $this->genieacs()->getParamValue($dev, 'model') ?? '-',
            'serial'        => $this->genieacs()->getParamValue($dev, 'serial_number') ?? '-',
            'firmware'      => $this->genieacs()->getParamValue($dev, 'firmware_version') ?? '-',
            'pppoe_user'    => $pppoeUser ?: '',
            'conn_status'   => $connStatus ?: '-',
            'external_ip'   => $externalIp ?: '-',
            'profile'       => $profile,
            'last_inform'   => isset($dev['_lastInform'])
                ? \Carbon\Carbon::parse($dev['_lastInform'])->diffForHumans()
                : '-',
        ]);
    }

    /**
     * Set PPPoE credentials on an unlinked GenieACS device and auto-link it to a PPP user.
     */
    public function setPppoeUnlinked(Request $request, string $genieacsId): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $validated = $request->validate([
            'ppp_user_id' => 'required|integer',
            'username'    => 'required|string|max:64',
            'password'    => 'required|string|max:64',
        ]);

        $user    = auth()->user();
        $pppUser = PppUser::query()->accessibleBy($user)->findOrFail($validated['ppp_user_id']);

        // Reject if PPP user already has another CPE device linked
        if ($pppUser->cpeDevice) {
            return response()->json([
                'success' => false,
                'message' => 'PPP user ini sudah memiliki perangkat CPE yang terhubung. Lepas dulu sebelum menghubungkan perangkat baru.',
            ], 422);
        }

        $deviceId  = rawurldecode($genieacsId);
        $genieDevice = $this->genieacs()->getDeviceInfo($deviceId);
        if (empty($genieDevice)) {
            return response()->json(['success' => false, 'message' => 'Device tidak ditemukan di GenieACS.'], 404);
        }

        $profile = $this->genieacs()->detectParamProfile($genieDevice);

        // Send PPPoE credentials to device
        try {
            $result = $this->genieacs()->setPppoeCredentials($deviceId, $validated['username'], $validated['password'], $profile);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengirim kredensial: '.$e->getMessage()], 500);
        }

        // Auto-link device to PPP user
        $device = new CpeDevice([
            'ppp_user_id' => $pppUser->id,
            'owner_id'    => $pppUser->owner_id,
        ]);
        $device->updateFromGenieacs($genieDevice);
        $device->save();

        $this->logActivity('set_pppoe_unlinked', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Kredensial PPPoE dikirim dan akan diterapkan saat perangkat online. Perangkat berhasil dihubungkan ke '.$pppUser->customer_name.'.'
            : 'Kredensial PPPoE berhasil diterapkan. Perangkat berhasil dihubungkan ke '.$pppUser->customer_name.'.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findPppUser(int $id): PppUser
    {
        $user    = auth()->user();
        $pppUser = PppUser::query()->accessibleBy($user)->findOrFail($id);

        return $pppUser;
    }

    private function requireCpeDevice(PppUser $pppUser): CpeDevice
    {
        $device = $pppUser->cpeDevice;

        if (! $device || ! $device->genieacs_device_id) {
            abort(422, 'Perangkat belum terhubung ke GenieACS. Lakukan sinkronisasi terlebih dahulu.');
        }

        return $device;
    }

    private function deviceToArray(CpeDevice $device): array
    {
        // PPPoE session status from radius_accounts (synced via MikroTik API)
        $pppoeOnline = false;
        $pppoeIp     = null;
        if ($device->pppUser) {
            try {
                $session = RadiusAccount::where('username', $device->pppUser->username)
                    ->where('is_active', true)
                    ->first(['ipv4_address']);
                $pppoeOnline = $session !== null;
                $pppoeIp     = $session?->ipv4_address;
            } catch (\Throwable) {
                // radius_accounts table may not exist in all environments
            }
        }

        // OLT signal lookup via WAN MAC → OltOnuOptic.serial_number
        $oltData = $this->lookupOltSignal($device);

        return [
            'id'                 => $device->id,
            'genieacs_device_id' => $device->genieacs_device_id,
            'serial_number'      => $device->serial_number,
            'manufacturer'       => $device->manufacturer,
            'model'              => $device->model,
            'firmware_version'   => $device->firmware_version,
            'status'             => $device->status ?? 'unknown',
            'last_seen_at'       => $device->last_seen_at?->diffForHumans(),
            'cached_params'      => $device->cached_params,
            'pppoe_online'       => $pppoeOnline,
            'pppoe_ip'           => $pppoeIp,
            'olt_rx_dbm'         => $oltData['rx_onu_dbm'],
            'olt_distance_m'     => $oltData['distance_m'],
            'olt_status'         => $oltData['status'],
            'olt_onu_optic_id'   => $oltData['olt_onu_optic_id'],
        ];
    }

    /**
     * Lookup OLT signal data.
     * Priority: manual link (olt_onu_optic_id) → MAC matching fallback.
     */
    private function lookupOltSignal(CpeDevice $device): array
    {
        $default = ['rx_onu_dbm' => null, 'distance_m' => null, 'status' => null, 'olt_onu_optic_id' => null];

        // Priority 1: manual link
        if ($device->olt_onu_optic_id) {
            $optic = OltOnuOptic::find($device->olt_onu_optic_id);
            if ($optic) {
                return [
                    'rx_onu_dbm'       => $optic->rx_onu_dbm,
                    'distance_m'       => $optic->distance_m,
                    'status'           => $optic->status,
                    'olt_onu_optic_id' => $optic->id,
                ];
            }
        }

        // Priority 2: MAC-based auto-match
        $wanMac = $device->cached_params['wan_mac'] ?? null;
        if (! $wanMac) {
            return $default;
        }

        $ownerId = $device->pppUser?->owner_id ?? $device->owner_id;

        $optic = OltOnuOptic::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('serial_number')
            ->get(['id', 'serial_number', 'rx_onu_dbm', 'distance_m', 'status'])
            ->first(function ($row) use ($wanMac) {
                $normalized = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $row->serial_number));
                return $normalized === $wanMac;
            });

        if (! $optic) {
            return $default;
        }

        return [
            'rx_onu_dbm'       => $optic->rx_onu_dbm,
            'distance_m'       => $optic->distance_m,
            'status'           => $optic->status,
            'olt_onu_optic_id' => $optic->id,
        ];
    }

    /**
     * Search OLT ONUs for manual link selection.
     */
    public function searchOltOnus(int $pppUserId, Request $request): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $user    = $request->user();

        if (! $user->isSuperAdmin() && $pppUser->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $q       = trim($request->string('q', ''));
        $ownerId = $pppUser->owner_id;

        $onus = OltOnuOptic::query()
            ->where('owner_id', $ownerId)
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('onu_name', 'like', "%{$q}%")
                      ->orWhere('serial_number', 'like', "%{$q}%");
            }))
            ->orderBy('onu_name')
            ->limit(30)
            ->get(['id', 'onu_name', 'serial_number', 'pon_interface', 'onu_number', 'rx_onu_dbm', 'status']);

        return response()->json(['status' => 'ok', 'data' => $onus]);
    }

    /**
     * Manually link or unlink a CPE device to an OLT ONU.
     */
    public function linkOltOnu(int $pppUserId, Request $request): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $user    = $request->user();

        if (! $user->isSuperAdmin() && $pppUser->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $device = $this->requireCpeDevice($pppUser);

        $opticId = $request->input('olt_onu_optic_id'); // null = unlink

        if ($opticId !== null) {
            $optic = OltOnuOptic::where('owner_id', $pppUser->owner_id)->find($opticId);
            if (! $optic) {
                return response()->json(['status' => 'error', 'message' => 'ONU tidak ditemukan.'], 404);
            }
            $device->olt_onu_optic_id = $optic->id;
            $device->save();

            return response()->json([
                'status'  => 'ok',
                'message' => 'ONU berhasil di-link.',
                'data'    => [
                    'olt_onu_optic_id' => $optic->id,
                    'onu_name'         => $optic->onu_name,
                    'rx_onu_dbm'       => $optic->rx_onu_dbm,
                    'distance_m'       => $optic->distance_m,
                    'status'           => $optic->status,
                ],
            ]);
        }

        // Unlink
        $device->olt_onu_optic_id = null;
        $device->save();

        return response()->json(['status' => 'ok', 'message' => 'Link ONU dihapus.']);
    }

    /**
     * Get OLT signal history for a CPE device.
     */
    public function getOltHistory(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        $oltData = $this->lookupOltSignal($device);
        if (! $oltData['olt_onu_optic_id']) {
            return response()->json(['status' => 'ok', 'data' => ['histories' => []]]);
        }

        $histories = OltOnuOpticHistory::query()
            ->where('olt_onu_optic_id', $oltData['olt_onu_optic_id'])
            ->orderByDesc('polled_at')
            ->limit(96)
            ->get(['polled_at', 'rx_onu_dbm', 'distance_m', 'status']);

        return response()->json([
            'status' => 'ok',
            'data'   => ['histories' => $histories],
        ]);
    }

    private function canManageCpe(): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc', 'it_support', 'teknisi']);
    }

    private function canRebootCpe(): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc', 'it_support', 'teknisi']);
    }

    private function canWifiCpe(): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc', 'it_support', 'teknisi']);
    }
}
