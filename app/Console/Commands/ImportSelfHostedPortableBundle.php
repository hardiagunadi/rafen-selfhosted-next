<?php

namespace App\Console\Commands;

use App\Services\SelfHostedPortableImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportSelfHostedPortableBundle extends Command
{
    protected $signature = 'self-hosted:import
        {stage : Directory hasil self-hosted:stage}
        {target : Root directory repo target}
        {--force : Timpa file target yang sudah ada}';

    protected $description = 'Import bundle portable self-hosted ke direktori repo target.';

    public function handle(SelfHostedPortableImportService $portableImportService): int
    {
        try {
            $result = $portableImportService->import(
                (string) $this->argument('stage'),
                (string) $this->argument('target'),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Portable self-hosted bundle berhasil diimport.');
        $this->line('Target Directory      : '.$result['target_directory']);
        $this->line('Copied File Count     : '.$result['copied_file_count']);
        $this->line('Overwritten File Count: '.$result['overwritten_file_count']);

        return self::SUCCESS;
    }
}
