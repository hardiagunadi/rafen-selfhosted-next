<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SelfHostedPortableImportService
{
    /**
     * @return array<string, mixed>
     */
    public function import(string $stageDirectory, string $targetDirectory, bool $force = false): array
    {
        $stageDirectory = rtrim($stageDirectory, DIRECTORY_SEPARATOR);
        $targetDirectory = rtrim($targetDirectory, DIRECTORY_SEPARATOR);

        if ($stageDirectory === '' || $targetDirectory === '') {
            throw new RuntimeException('Stage directory dan target directory wajib diisi.');
        }

        $manifestPath = $stageDirectory.'/manifest.json';
        $portableRoot = $stageDirectory.'/portable';
        $updateNoticePath = $stageDirectory.'/_self_hosted_update_notice.json';

        if (! File::exists($manifestPath) || ! File::isDirectory($portableRoot)) {
            throw new RuntimeException('Stage directory tidak valid atau belum berisi bundle self-hosted.');
        }

        $manifest = json_decode((string) File::get($manifestPath), true);
        $portableDirectories = $manifest['portable_directories'] ?? [];

        if (! is_array($manifest)
            || ! is_array($manifest['portable_files'] ?? null)
            || ! is_array($portableDirectories)) {
            throw new RuntimeException('Manifest staging tidak valid.');
        }

        File::ensureDirectoryExists($targetDirectory);

        $overwrittenFiles = 0;
        $copiedFiles = 0;
        $overwrittenDirectories = 0;
        $copiedDirectories = 0;

        foreach ($manifest['portable_files'] as $relativePath) {
            if (! is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $source = $portableRoot.'/'.$relativePath;
            $destination = $targetDirectory.'/'.$relativePath;

            if (! File::exists($source)) {
                throw new RuntimeException("Portable source file tidak ditemukan: {$relativePath}");
            }

            if (File::exists($destination) && ! $force) {
                throw new RuntimeException("File target sudah ada: {$relativePath}. Gunakan --force untuk menimpa.");
            }

            File::ensureDirectoryExists(dirname($destination));

            if (File::exists($destination)) {
                $overwrittenFiles++;
            }

            File::copy($source, $destination);
            $copiedFiles++;
        }

        foreach ($portableDirectories as $relativePath) {
            if (! is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $source = $portableRoot.'/'.$relativePath;
            $destination = $targetDirectory.'/'.$relativePath;

            if (! File::isDirectory($source)) {
                throw new RuntimeException("Portable source directory tidak ditemukan: {$relativePath}");
            }

            if (File::exists($destination) && ! $force) {
                throw new RuntimeException("Directory target sudah ada: {$relativePath}. Gunakan --force untuk menimpa.");
            }

            File::ensureDirectoryExists(dirname($destination));

            if (File::isDirectory($destination)) {
                File::deleteDirectory($destination);
                $overwrittenDirectories++;
            } elseif (File::exists($destination)) {
                File::delete($destination);
                $overwrittenDirectories++;
            }

            File::copyDirectory($source, $destination);
            $copiedDirectories++;
        }

        File::put(
            $targetDirectory.'/.self-hosted-import.json',
            json_encode([
                'imported_at' => now()->toIso8601String(),
                'source_stage_directory' => $stageDirectory,
                'portable_file_count' => $copiedFiles,
                'portable_directory_count' => $copiedDirectories,
                'overwritten_file_count' => $overwrittenFiles,
                'overwritten_directory_count' => $overwrittenDirectories,
                'update_notice_path' => File::exists($updateNoticePath) ? $targetDirectory.'/_self_hosted_update_notice.json' : null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if (File::exists($updateNoticePath)) {
            File::copy($updateNoticePath, $targetDirectory.'/_self_hosted_update_notice.json');
        }

        return [
            'target_directory' => $targetDirectory,
            'copied_file_count' => $copiedFiles,
            'copied_directory_count' => $copiedDirectories,
            'overwritten_file_count' => $overwrittenFiles,
            'overwritten_directory_count' => $overwrittenDirectories,
            'update_notice_path' => File::exists($updateNoticePath) ? $targetDirectory.'/_self_hosted_update_notice.json' : null,
        ];
    }
}
