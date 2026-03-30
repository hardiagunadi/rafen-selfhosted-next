<?php

namespace App\Console\Commands;

use App\Services\SelfHostedExtractionManifestService;
use Illuminate\Console\Command;

class ShowSelfHostedExtractionManifest extends Command
{
    protected $signature = 'self-hosted:manifest {--json : Output the extraction manifest as JSON}';

    protected $description = 'Tampilkan manifest file dan dependency cluster self-hosted yang siap dipindah ke repo terpisah.';

    public function handle(SelfHostedExtractionManifestService $manifestService): int
    {
        $manifest = $manifestService->build();

        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Self-Hosted Extraction Manifest');
        $this->line('Destination Repo : '.$manifest['destination_repo']);
        $this->line('Feature Flag     : '.$manifest['feature_flag']);
        $this->newLine();

        foreach ([
            'env_vars' => 'Env Vars',
            'config' => 'Config',
            'providers' => 'Providers',
            'routes' => 'Routes',
            'controllers' => 'Controllers',
            'requests' => 'Requests',
            'middleware' => 'Middleware',
            'models' => 'Models',
            'services' => 'Services',
            'commands' => 'Commands',
            'views' => 'Views',
            'database' => 'Database',
            'reference_tests' => 'Reference Tests',
            'post_extraction_cleanup' => 'Post Extraction Cleanup',
            'retain_in_saas' => 'Retain In SaaS',
        ] as $key => $label) {
            $this->line($label.':');

            foreach ($manifest[$key] as $item) {
                $this->line('  - '.$item);
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
