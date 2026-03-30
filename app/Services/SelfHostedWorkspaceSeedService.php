<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SelfHostedWorkspaceSeedService
{
    public function __construct(
        private readonly SelfHostedCutoverPlanService $cutoverPlanService,
        private readonly SelfHostedExtractionManifestService $manifestService,
        private readonly SelfHostedExtractionStagingService $stagingService,
        private readonly SelfHostedPortableImportService $portableImportService,
        private readonly SelfHostedWorkspaceScaffoldService $scaffoldService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(string $targetDirectory, bool $force = false): array
    {
        $targetDirectory = rtrim($targetDirectory, DIRECTORY_SEPARATOR);

        if ($targetDirectory === '') {
            throw new RuntimeException('Target workspace self-hosted tidak boleh kosong.');
        }

        if (File::exists($targetDirectory) && ! $force && count(File::allFiles($targetDirectory)) > 0) {
            throw new RuntimeException('Target workspace sudah berisi file. Gunakan --force untuk membuat ulang.');
        }

        if ($force && File::exists($targetDirectory)) {
            File::deleteDirectory($targetDirectory);
        }

        File::ensureDirectoryExists($targetDirectory);

        $stageDirectory = storage_path('framework/self-hosted-workspace-stage-'.md5($targetDirectory));

        try {
            $this->stagingService->stage($stageDirectory, true);
            $importResult = $this->portableImportService->import($stageDirectory, $targetDirectory, true);
            $scaffoldResult = $this->scaffoldService->scaffold($targetDirectory, true);

            File::copyDirectory($stageDirectory.'/references', $targetDirectory.'/_integration-references');
            File::copy($stageDirectory.'/manifest.json', $targetDirectory.'/_self_hosted_manifest.json');
            File::put(
                $targetDirectory.'/_self_hosted_cutover_plan.json',
                json_encode($this->cutoverPlanService->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            File::put(
                $targetDirectory.'/_self_hosted_workspace_seed.json',
                json_encode([
                    'created_at' => now()->toIso8601String(),
                    'source_repo' => 'rafen-saas',
                    'destination_repo' => $this->manifestService->build()['destination_repo'],
                    'target_directory' => $targetDirectory,
                    'copied_file_count' => $importResult['copied_file_count'],
                    'scaffold_file_count' => $scaffoldResult['written_file_count'],
                    'update_notice_path' => $importResult['update_notice_path'] ?? null,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } finally {
            File::deleteDirectory($stageDirectory);
        }

        return [
            'target_directory' => $targetDirectory,
            'copied_file_count' => $importResult['copied_file_count'],
            'scaffold_file_count' => $scaffoldResult['written_file_count'],
            'references_directory' => $targetDirectory.'/_integration-references',
            'update_notice_path' => $importResult['update_notice_path'] ?? null,
        ];
    }
}
