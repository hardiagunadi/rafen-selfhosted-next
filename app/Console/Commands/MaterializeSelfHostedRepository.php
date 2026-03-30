<?php

namespace App\Console\Commands;

use App\Services\SelfHostedRepositoryMaterializationService;
use Illuminate\Console\Command;
use RuntimeException;

class MaterializeSelfHostedRepository extends Command
{
    protected $signature = 'self-hosted:materialize-repo
        {target : Directory target candidate repo self-hosted}
        {--force : Timpa target yang sudah ada}';

    protected $description = 'Bentuk candidate repo self-hosted dari workspace seed dan skeleton root Laravel.';

    public function handle(SelfHostedRepositoryMaterializationService $materializationService): int
    {
        try {
            $result = $materializationService->materialize(
                (string) $this->argument('target'),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Candidate repo self-hosted berhasil dibuat.');
        $this->line('Target Directory      : '.$result['target_directory']);
        $this->line('Seed Portable Files   : '.$result['seed_copied_file_count']);
        $this->line('Seed Scaffold Files   : '.$result['seed_scaffold_file_count']);
        $this->line('Skeleton Root Files   : '.$result['skeleton_file_count']);
        $this->line('Skeleton Directories  : '.$result['skeleton_directory_count']);

        return self::SUCCESS;
    }
}
