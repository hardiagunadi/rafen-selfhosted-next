<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class LicenseFingerprintService
{
    public function generate(): string
    {
        $payload = [
            'app_url_host' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            'app_env' => (string) config('app.env'),
            'machine_id' => $this->resolveMachineId(),
            'server_name' => php_uname('n'),
            'app_key_hash' => hash('sha256', (string) config('app.key')),
        ];

        return 'sha256:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function resolveMachineId(): string
    {
        $machineIdPath = (string) config('license.machine_id_path');

        if ($machineIdPath !== '' && File::exists($machineIdPath)) {
            $machineId = trim((string) File::get($machineIdPath));

            if ($machineId !== '') {
                return $machineId;
            }
        }

        return php_uname('n');
    }
}
