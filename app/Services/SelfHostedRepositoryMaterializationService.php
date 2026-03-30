<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SelfHostedRepositoryMaterializationService
{
    public function __construct(
        private readonly SelfHostedWorkspaceSeedService $workspaceSeedService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function materialize(string $targetDirectory, bool $force = false): array
    {
        $targetDirectory = rtrim($targetDirectory, DIRECTORY_SEPARATOR);

        if ($targetDirectory === '') {
            throw new RuntimeException('Target repo self-hosted tidak boleh kosong.');
        }

        if (File::exists($targetDirectory) && ! $force && count(File::allFiles($targetDirectory)) > 0) {
            throw new RuntimeException('Target repo sudah berisi file. Gunakan --force untuk membuat ulang.');
        }

        if ($force && File::exists($targetDirectory)) {
            File::deleteDirectory($targetDirectory);
        }

        $seedResult = $this->workspaceSeedService->create($targetDirectory, true);
        $copiedFiles = 0;
        $copiedDirectories = 0;

        foreach ($this->skeletonFiles() as $relativePath) {
            $source = base_path($relativePath);
            $destination = $targetDirectory.'/'.$relativePath;

            if (! File::exists($source)) {
                throw new RuntimeException("File skeleton tidak ditemukan: {$relativePath}");
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($source, $destination);
            $copiedFiles++;
        }

        foreach ($this->skeletonDirectories() as $relativePath) {
            $source = base_path($relativePath);
            $destination = $targetDirectory.'/'.$relativePath;

            if (! File::isDirectory($source)) {
                throw new RuntimeException("Directory skeleton tidak ditemukan: {$relativePath}");
            }

            File::deleteDirectory($destination);
            File::copyDirectory($source, $destination);
            $copiedDirectories++;
        }

        File::put(
            $targetDirectory.'/_self_hosted_repository_candidate.json',
            json_encode([
                'generated_at' => now()->toIso8601String(),
                'target_directory' => $targetDirectory,
                'seed_copied_file_count' => $seedResult['copied_file_count'],
                'seed_scaffold_file_count' => $seedResult['scaffold_file_count'],
                'skeleton_file_count' => $copiedFiles,
                'skeleton_directory_count' => $copiedDirectories,
                'update_notice_path' => $seedResult['update_notice_path'] ?? null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [
            'target_directory' => $targetDirectory,
            'seed_copied_file_count' => $seedResult['copied_file_count'],
            'seed_scaffold_file_count' => $seedResult['scaffold_file_count'],
            'skeleton_file_count' => $copiedFiles,
            'skeleton_directory_count' => $copiedDirectories,
            'update_notice_path' => $seedResult['update_notice_path'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    private function skeletonFiles(): array
    {
        return [
            '.editorconfig',
            '.gitattributes',
            '.gitignore',
            'artisan',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'phpunit.xml',
            'vite.config.js',
            'app/helpers.php',
        ];
    }

    /**
     * @return list<string>
     */
    private function skeletonDirectories(): array
    {
        return [
            'config',
            'public',
        ];
    }
}
