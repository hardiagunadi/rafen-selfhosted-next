<?php

namespace App\Console\Commands;

use App\Services\SelfHostedUpdateNoticeMetadataService;
use Illuminate\Console\Command;

class PublishSelfHostedUpdateNotice extends Command
{
    protected $signature = 'self-hosted:publish-update-notice
        {path? : Lokasi output file metadata update}
        {--json : Tampilkan payload metadata sebagai JSON}';

    protected $description = 'Buat file metadata _self_hosted_update_notice.json yang siap dikirim ke instance self-hosted.';

    public function handle(SelfHostedUpdateNoticeMetadataService $metadataService): int
    {
        $path = (string) ($this->argument('path') ?: storage_path('framework/self-hosted-update-notice/_self_hosted_update_notice.json'));
        $payload = $metadataService->write($path);

        $this->info('Metadata update self-hosted berhasil dipublikasikan.');
        $this->line('Output Path       : '.$path);
        $this->line('Available Version : '.($payload['available_version'] ?? '-'));

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
