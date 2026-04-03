<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SelfHostedExtractionStagingService
{
    public function __construct(
        private readonly SelfHostedExtractionManifestService $manifestService,
        private readonly SelfHostedUpdateNoticeMetadataService $updateNoticeMetadataService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stage(string $targetDirectory, bool $force = false): array
    {
        $manifest = $this->manifestService->build();
        $portableDirectories = $manifest['portable_directories'] ?? [];
        $targetDirectory = rtrim($targetDirectory, DIRECTORY_SEPARATOR);

        if ($targetDirectory === '') {
            throw new RuntimeException('Target directory staging tidak boleh kosong.');
        }

        if (File::exists($targetDirectory) && ! $force && count(File::allFiles($targetDirectory)) > 0) {
            throw new RuntimeException('Target directory staging sudah berisi file. Gunakan --force untuk menimpa.');
        }

        if ($force && File::exists($targetDirectory)) {
            File::deleteDirectory($targetDirectory);
        }

        File::ensureDirectoryExists($targetDirectory);

        $portableRoot = $targetDirectory.'/portable';
        $referencesRoot = $targetDirectory.'/references';

        File::ensureDirectoryExists($portableRoot);
        File::ensureDirectoryExists($referencesRoot);

        foreach ($manifest['portable_files'] as $path) {
            $this->copyRelativePath(base_path($path), $portableRoot.'/'.$path);
        }

        foreach ($portableDirectories as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $this->copyRelativeDirectory(base_path($path), $portableRoot.'/'.$path);
        }

        foreach ($manifest['integration_touchpoints'] as $path) {
            $this->copyRelativePath(base_path($path), $referencesRoot.'/'.$path);
        }

        File::put(
            $targetDirectory.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $updateNoticePath = $targetDirectory.'/_self_hosted_update_notice.json';
        $this->updateNoticeMetadataService->write($updateNoticePath);

        return [
            'target_directory' => $targetDirectory,
            'portable_root' => $portableRoot,
            'references_root' => $referencesRoot,
            'update_notice_path' => $updateNoticePath,
            'portable_file_count' => count($manifest['portable_files']),
            'portable_directory_count' => count($portableDirectories),
            'integration_touchpoint_count' => count($manifest['integration_touchpoints']),
        ];
    }

    private function copyRelativePath(string $source, string $destination): void
    {
        if (! File::exists($source)) {
            throw new RuntimeException("Source extraction file tidak ditemukan: {$source}");
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);
    }

    private function copyRelativeDirectory(string $source, string $destination): void
    {
        if (! File::isDirectory($source)) {
            throw new RuntimeException("Source extraction directory tidak ditemukan: {$source}");
        }

        File::deleteDirectory($destination);
        File::ensureDirectoryExists(dirname($destination));
        File::copyDirectory($source, $destination);
    }
}
