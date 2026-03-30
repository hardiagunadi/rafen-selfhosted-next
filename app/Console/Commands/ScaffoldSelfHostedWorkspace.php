<?php

namespace App\Console\Commands;

use App\Services\SelfHostedWorkspaceScaffoldService;
use Illuminate\Console\Command;
use RuntimeException;

class ScaffoldSelfHostedWorkspace extends Command
{
    protected $signature = 'self-hosted:scaffold-workspace
        {target : Directory workspace self-hosted}
        {--force : Timpa scaffold yang sudah ada}';

    protected $description = 'Tambahkan scaffold starter ke workspace self-hosted.';

    public function handle(SelfHostedWorkspaceScaffoldService $scaffoldService): int
    {
        try {
            $result = $scaffoldService->scaffold(
                (string) $this->argument('target'),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Scaffold workspace self-hosted berhasil dibuat.');
        $this->line('Target Directory : '.$result['target_directory']);
        $this->line('Written Files    : '.$result['written_file_count']);

        return self::SUCCESS;
    }
}
