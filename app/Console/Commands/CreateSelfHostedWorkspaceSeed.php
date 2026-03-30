<?php

namespace App\Console\Commands;

use App\Services\SelfHostedWorkspaceSeedService;
use Illuminate\Console\Command;
use RuntimeException;

class CreateSelfHostedWorkspaceSeed extends Command
{
    protected $signature = 'self-hosted:seed-workspace
        {target : Directory tujuan workspace seed}
        {--force : Hapus workspace lama lalu buat ulang}';

    protected $description = 'Buat workspace seed self-hosted lengkap dari repo SaaS saat ini.';

    public function handle(SelfHostedWorkspaceSeedService $workspaceSeedService): int
    {
        try {
            $result = $workspaceSeedService->create(
                (string) $this->argument('target'),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Workspace seed self-hosted berhasil dibuat.');
        $this->line('Target Directory   : '.$result['target_directory']);
        $this->line('Copied File Count  : '.$result['copied_file_count']);
        $this->line('References Folder  : '.$result['references_directory']);

        return self::SUCCESS;
    }
}
