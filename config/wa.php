<?php

return [
    'multi_session' => [
        'path' => env('WA_MULTI_SESSION_PATH', base_path('wa-multi-session')),
        'host' => env('WA_MULTI_SESSION_HOST', '127.0.0.1'),
        'port' => (int) env('WA_MULTI_SESSION_PORT', 3100),
        'auth_token' => env('WA_MULTI_SESSION_AUTH_TOKEN', ''),
        'master_key' => env('WA_MULTI_SESSION_MASTER_KEY', ''),
        'proxy_path' => env('WA_MULTI_SESSION_PROXY_PATH', '/wa-multi-session'),
        'public_url' => env('WA_MULTI_SESSION_PUBLIC_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/wa-multi-session'),
        'pm2_bin' => env('WA_MULTI_SESSION_PM2_BIN', 'pm2'),
        'pm2_home' => env('WA_MULTI_SESSION_PM2_HOME', '/var/www/.pm2'),
        'pm2_name' => env('WA_MULTI_SESSION_PM2_NAME', 'wa-multi-session'),
        'log_file' => env('WA_MULTI_SESSION_LOG_FILE', storage_path('logs/wa-multi-session-pm2.log')),
        'script' => env('WA_MULTI_SESSION_SCRIPT', 'gateway-server.cjs'),
        'db_table' => env('WA_MULTI_SESSION_DB_TABLE', 'wa_multi_session_auth_store'),
        'webhook_url' => env('WA_MULTI_SESSION_WEBHOOK_URL', ''),
    ],
];
