<?php

return [
    'nbi_url'  => env('GENIEACS_NBI_URL', 'http://localhost:7557'),
    'ui_url'   => env('GENIEACS_UI_URL', 'http://localhost:3000'),
    'username' => env('GENIEACS_NBI_USERNAME', ''),
    'password' => env('GENIEACS_NBI_PASSWORD', ''),
    'timeout'  => (int) env('GENIEACS_NBI_TIMEOUT', 10),

    /*
    | Minutes since last TR-069 inform before a device is considered offline.
    | Default 70 = slightly over 1-hour inform interval (GenieACS default).
    | Tune with GENIEACS_ONLINE_THRESHOLD_MINUTES in .env.
    */
    'online_threshold_minutes' => (int) env('GENIEACS_ONLINE_THRESHOLD_MINUTES', 70),

    /*
    | CPE Auto-Recovery: kirim Connection Request ke device offline agar
    | TR-069 client di modem membuka sesi baru (mengatasi stuck PeriodicInform).
    | min_offline_hours  — minimum jam offline sebelum dicoba recovery (default 2)
    | retry_hours        — jeda minimum antar percobaan per device (default 4)
    | batch_size         — maksimum device per satu run (default 20)
    */
    'recovery_min_offline_hours' => (int) env('GENIEACS_RECOVERY_MIN_OFFLINE_HOURS', 2),
    'recovery_retry_hours'       => (int) env('GENIEACS_RECOVERY_RETRY_HOURS', 4),
    'recovery_batch_size'        => (int) env('GENIEACS_RECOVERY_BATCH_SIZE', 20),

    /*
    | Default presets pushed to GenieACS for all CPE devices.
    | These ensure GenieACS can trigger Connection Request (immediate task execution)
    | and that devices inform frequently enough for timely task delivery.
    */
    'connection_request_username' => env('GENIEACS_CR_USERNAME', 'rafen'),
    'connection_request_password' => env('GENIEACS_CR_PASSWORD', 'rafen2024'),
    'inform_interval'             => (int) env('GENIEACS_INFORM_INTERVAL', 300),

    /*
    |--------------------------------------------------------------------------
    | TR-069 Parameter Paths
    |--------------------------------------------------------------------------
    | Two profiles supported:
    |   - "igd" : InternetGatewayDevice.* (TR-098, older CPE — most XPON/EPON ONUs)
    |   - "device" : Device.* (TR-181 Device:2, newer CPE)
    |
    | GenieAcsClient auto-detects which root the device uses.
    */
    'params' => [
        // TR-098 (InternetGatewayDevice) paths — used by H3-2S XPON and similar
        'igd' => [
            'wan_object'       => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1',
            'pppoe_username'   => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username',
            'pppoe_password'   => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password',
            'wifi_ssid'        => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'wifi_password'    => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
            'firmware_version' => 'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
            'model'            => 'InternetGatewayDevice.DeviceInfo.ModelName',
            'manufacturer'     => 'InternetGatewayDevice.DeviceInfo.Manufacturer',
            'serial_number'    => 'InternetGatewayDevice.DeviceInfo.SerialNumber',
            'uptime'           => 'InternetGatewayDevice.DeviceInfo.UpTime',
            // Multi-SSID — {idx} replaced at runtime with instance number (1,3,5,7,...)
            'wifi_ssid_n'      => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.{idx}.SSID',
            'wifi_password_n'  => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.{idx}.KeyPassphrase',
            'wifi_enable_n'    => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.{idx}.Enable',
            // WAN — {wd}.{cd}.{conn} replaced at runtime
            'wan_username'     => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.Username',
            'wan_password'     => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.Password',
            'wan_enable'       => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.Enable',
            'wan_nat'          => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.NATEnabled',
            'wan_dns'          => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.DNSServers',
            'wan_conn_type'    => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.ConnectionType',
            'wan_vlan'         => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.X_CT-COM_WANEponLinkConfig.VLANIDMark',
            'wan_vlan_prio'    => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.X_CT-COM_WANEponLinkConfig.802-1pMark',
            'wan_lan_iface'    => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.X_CT-COM_LanInterface',
        ],
        // TR-181 (Device) paths — newer CPE
        'device' => [
            'wan_object'       => 'Device.PPP.Interface',
            'pppoe_username'   => 'Device.PPP.Interface.1.Username',
            'pppoe_password'   => 'Device.PPP.Interface.1.Password',
            'wifi_ssid'        => 'Device.WiFi.SSID.1.SSID',
            'wifi_password'    => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
            'firmware_version' => 'Device.DeviceInfo.SoftwareVersion',
            'model'            => 'Device.DeviceInfo.ModelName',
            'manufacturer'     => 'Device.DeviceInfo.Manufacturer',
            'serial_number'    => 'Device.DeviceInfo.SerialNumber',
            'uptime'           => 'Device.DeviceInfo.UpTime',
            // Multi-SSID — {idx} replaced at runtime
            'wifi_ssid_n'      => 'Device.WiFi.SSID.{idx}.SSID',
            'wifi_password_n'  => 'Device.WiFi.AccessPoint.{idx}.Security.KeyPassphrase',
            'wifi_enable_n'    => 'Device.WiFi.SSID.{idx}.Enable',
            // WAN PPP — {conn} replaced at runtime (TR-181 uses flat PPP.Interface list)
            'wan_username'     => 'Device.PPP.Interface.{conn}.Username',
            'wan_password'     => 'Device.PPP.Interface.{conn}.Password',
            'wan_enable'       => 'Device.PPP.Interface.{conn}.Enable',
        ],
    ],
];
