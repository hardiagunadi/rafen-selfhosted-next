<?php

namespace App\Console\Commands;

use App\Services\AppBuildMetadataService;
use Illuminate\Console\Command;

class SyncSelfHostedBuildMetadata extends Command
{
    protected $signature = 'self-hosted:sync-build-metadata
        {--env-path= : Lokasi file .env target}
        {--release-version= : Override APP_VERSION}
        {--commit= : Override APP_COMMIT}
        {--json : Tampilkan hasil sinkronisasi sebagai JSON}';

    protected $description = 'Sinkronkan APP_VERSION dan APP_COMMIT ke file .env instance self-hosted.';

    public function handle(AppBuildMetadataService $buildMetadataService): int
    {
        $result = $buildMetadataService->syncEnvFile(
            (string) ($this->option('env-path') ?: base_path('.env')),
            $this->option('release-version'),
            $this->option('commit'),
        );

        $this->info('Metadata build self-hosted berhasil disinkronkan.');
        $this->line('Env Path          : '.$result['env_path']);
        $this->line('APP_VERSION       : '.$result['version']);
        $this->line('APP_COMMIT        : '.($result['commit'] ?? '-'));

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
