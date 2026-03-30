<?php

namespace App\Console\Commands;

use App\Services\LicenseActivationRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSystemLicenseActivationRequest extends Command
{
    protected $signature = 'license:activation-request {--path= : Simpan activation request ke path file tertentu}';

    protected $description = 'Generate activation request untuk lisensi sistem self-hosted.';

    public function handle(LicenseActivationRequestService $activationRequestService): int
    {
        $payload = $activationRequestService->makePayload();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json);

            $this->info('Activation request disimpan ke: '.$path);

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
