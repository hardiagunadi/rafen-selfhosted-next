<?php

namespace App\Console\Commands;

use App\Services\SelfHostedExtractionStagingService;
use Illuminate\Console\Command;
use RuntimeException;

class StageSelfHostedExtraction extends Command
{
    protected $signature = 'self-hosted:stage
        {target : Directory tujuan staging extraction}
        {--force : Hapus isi target lama sebelum staging}';

    protected $description = 'Stage cluster self-hosted ke direktori target agar mudah dipindah ke repo terpisah.';

    public function handle(SelfHostedExtractionStagingService $stagingService): int
    {
        try {
            $result = $stagingService->stage(
                (string) $this->argument('target'),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Self-hosted extraction berhasil di-stage.');
        $this->line('Target Directory           : '.$result['target_directory']);
        $this->line('Portable Root              : '.$result['portable_root']);
        $this->line('Integration References     : '.$result['references_root']);
        $this->line('Portable File Count        : '.$result['portable_file_count']);
        $this->line('Integration Touchpoint Count: '.$result['integration_touchpoint_count']);

        return self::SUCCESS;
    }
}
