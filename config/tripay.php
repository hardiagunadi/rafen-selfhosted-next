<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tripay API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Tripay payment gateway integration.
    | These are system-level credentials for subscription payments.
    | Tenant-level credentials are stored in tenant_settings table.
    |
    */

    'api_key' => env('TRIPAY_API_KEY', ''),
    'private_key' => env('TRIPAY_PRIVATE_KEY', ''),
    'merchant_code' => env('TRIPAY_MERCHANT_CODE', ''),
    'sandbox' => env('TRIPAY_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Callback Configuration
    |--------------------------------------------------------------------------
    */
    'callback_url' => env('TRIPAY_CALLBACK_URL', '/payment/callback'),
    'subscription_callback_url' => env('TRIPAY_SUBSCRIPTION_CALLBACK_URL', '/subscription/payment/callback'),

    /*
    |--------------------------------------------------------------------------
    | Payment Expiry
    |--------------------------------------------------------------------------
    */
    'default_expiry_hours' => env('TRIPAY_DEFAULT_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Enabled Payment Channels
    |--------------------------------------------------------------------------
    |
    | Default enabled payment channels for the system.
    | Tenants can configure their own channels.
    |
    */
    'enabled_channels' => [
        'QRIS',      // QRIS (All supported apps)
        'BRIVA',     // BRI Virtual Account
        'BCAVA',     // BCA Virtual Account
        'BNIVA',     // BNI Virtual Account
        'MANDIRIVA', // Mandiri Virtual Account
    ],
];
