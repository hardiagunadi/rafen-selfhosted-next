<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    ],

    'meta_whatsapp' => [
        'api_version' => env('META_WHATSAPP_API_VERSION', 'v23.0'),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN', ''),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID', ''),
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
        'app_secret' => env('META_WHATSAPP_APP_SECRET', ''),
    ],

];
