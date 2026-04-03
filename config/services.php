<?php

$turnstileSiteKey = (string) env('TURNSTILE_SITE_KEY', '');
$turnstileSecretKey = (string) env('TURNSTILE_SECRET_KEY', '');

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
        'site_key' => $turnstileSiteKey,
        'secret_key' => $turnstileSecretKey,
        'enabled' => $turnstileSiteKey !== '' && $turnstileSecretKey !== '',
    ],

    'meta_whatsapp' => [
        'api_version' => env('META_WHATSAPP_API_VERSION', 'v23.0'),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN', ''),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID', ''),
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
        'app_secret' => env('META_WHATSAPP_APP_SECRET', ''),
    ],

    'self_hosted_registry' => [
        'url' => env('SELF_HOSTED_REGISTRY_URL', ''),
        'token' => env('SELF_HOSTED_REGISTRY_TOKEN', ''),
        'heartbeat_stale_after_minutes' => (int) env('SELF_HOSTED_HEARTBEAT_STALE_AFTER_MINUTES', 60),
    ],

    'self_hosted_update' => [
        'manifest_url' => env('SELF_HOSTED_UPDATE_MANIFEST_URL', ''),
        'channel' => env('SELF_HOSTED_UPDATE_CHANNEL', 'stable'),
        'repository' => env('SELF_HOSTED_UPDATE_REPOSITORY', 'git@github.com:hardiagunadi/rafen-selfhosted-next.git'),
        'workdir' => env('SELF_HOSTED_UPDATE_WORKDIR', base_path()),
        'ignore_dirty_worktree' => (bool) env('SELF_HOSTED_UPDATE_IGNORE_DIRTY_WORKTREE', false),
    ],

];
