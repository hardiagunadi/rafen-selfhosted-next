<?php

$configuredAlarmOids = array_values(array_filter(array_map(
    static fn (string $oid): string => trim($oid),
    explode(',', (string) env('OLT_ALARM_OIDS', ''))
)));

$defaultAlarmDiscoveryRoots = [
    '1.3.6.1.4.1.5875.800',
    '1.3.6.1.4.1.50224',
];

return [
    'polling' => [
        'queue' => env('OLT_POLL_QUEUE', 'default'),
        'lock_seconds' => (int) env('OLT_POLL_LOCK_SECONDS', 900),
        'parallel_walk_batch' => (int) env('OLT_POLL_PARALLEL_WALK_BATCH', 3),
        'live_refresh_seconds' => (int) env('OLT_POLL_LIVE_REFRESH_SECONDS', 30),
        'full_refresh_seconds' => (int) env('OLT_POLL_FULL_REFRESH_SECONDS', 300),
    ],

    'alarm' => [
        'oids' => $configuredAlarmOids,
        'discovery_roots' => $defaultAlarmDiscoveryRoots,
        'max_entries' => (int) env('OLT_ALARM_MAX_ENTRIES', 50),
        'snmp_timeout' => (int) env('OLT_ALARM_SNMP_TIMEOUT', 2),
        'snmp_retries' => (int) env('OLT_ALARM_SNMP_RETRIES', 0),
        'cloud' => [
            'enabled' => (bool) env('OLT_ALARM_CLOUD_ENABLED', true),
            'url' => (string) env('OLT_ALARM_CLOUD_URL', 'https://www.hsgqcloud.com/v1/device/alarm'),
            'token' => (string) env('OLT_ALARM_CLOUD_TOKEN', ''),
            'timeout' => (int) env('OLT_ALARM_CLOUD_TIMEOUT', 8),
            'page_size' => (int) env('OLT_ALARM_CLOUD_PAGE_SIZE', 50),
            'max_pages' => (int) env('OLT_ALARM_CLOUD_MAX_PAGES', 5),
        ],
    ],

    'hsgq_models' => [
        'HSGQ-E04I (EPON)' => [
            'oids' => [
                'oid_serial' => '1.3.6.1.4.1.50224.3.3.2.1.7',
                'oid_onu_name' => '1.3.6.1.4.1.50224.3.3.2.1.2',
                'oid_rx_onu' => '1.3.6.1.4.1.50224.3.3.3.1.4',
                'oid_tx_onu' => '1.3.6.1.4.1.50224.3.3.3.1.5',
                'oid_rx_olt' => '1.3.6.1.4.1.50224.3.2.4.1.12',
                'oid_tx_olt' => '1.3.6.1.4.1.50224.3.2.4.1.11',
                'oid_distance' => '1.3.6.1.4.1.50224.3.3.2.1.15',
                'oid_status' => '1.3.6.1.4.1.50224.3.3.2.1.8',
                'oid_reboot_onu' => '1.3.6.1.4.1.50224.3.3.2.1.9',
            ],
        ],
        'HSGQ EPON' => [
            'oids' => [
                'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
                'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
                'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
                'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
                'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
                'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
                'oid_reboot_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.19',
            ],
        ],
        'HSGQ GPON' => [
            'oids' => [
                'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
                'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
                'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
                'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
                'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
                'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
                'oid_reboot_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.19',
            ],
        ],
        'HSGQ GPON 4 PON' => [
            'oids' => [
                'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
                'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
                'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
                'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
                'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
                'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
                'oid_reboot_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.19',
            ],
        ],
        'HSGQ GPON 8 PON' => [
            'oids' => [
                'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
                'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
                'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
                'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
                'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
                'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
                'oid_reboot_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.19',
            ],
        ],
        'HSGQ GPON 16 PON' => [
            'oids' => [
                'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
                'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
                'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
                'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
                'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
                'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
                'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
                'oid_reboot_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.19',
            ],
        ],
    ],
];
