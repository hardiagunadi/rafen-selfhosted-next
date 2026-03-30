<?php

return [
    'self_hosted_enabled' => env('LICENSE_SELF_HOSTED_ENABLED', false),
    'enforce' => env('LICENSE_ENFORCE', false),
    'public_key' => env('LICENSE_PUBLIC_KEY'),
    'public_key_editable' => env('LICENSE_PUBLIC_KEY_EDITABLE', false),
    'private_key_path' => env('LICENSE_PRIVATE_KEY_PATH', storage_path('app/license-signing/ed25519-private.key')),
    'path' => env('LICENSE_FILE_PATH', storage_path('app/license/rafen.lic')),
    'machine_id_path' => env('LICENSE_MACHINE_ID_PATH', '/etc/machine-id'),
    'default_grace_days' => (int) env('LICENSE_DEFAULT_GRACE_DAYS', 21),
];
