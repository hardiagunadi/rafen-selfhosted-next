<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Finder\Finder;

class SelfHostedWorkspaceAuditService
{
    /**
     * @return array<string, mixed>
     */
    public function audit(string $workspaceDirectory): array
    {
        $workspaceDirectory = rtrim($workspaceDirectory, DIRECTORY_SEPARATOR);

        if ($workspaceDirectory === '' || ! File::isDirectory($workspaceDirectory)) {
            throw new RuntimeException('Workspace self-hosted tidak ditemukan.');
        }

        $phpFiles = collect(
            Finder::create()
                ->files()
                ->in($workspaceDirectory)
                ->name('*.php')
                ->exclude(['vendor', 'node_modules', 'bootstrap/cache'])
        )
            ->values();

        $portableRuntimeMissingDependencies = [];
        $testMissingDependencies = [];
        $referenceMissingDependencies = [];

        foreach ($phpFiles as $file) {
            $absolutePath = $file->getPathname();
            $relativePath = str_replace($workspaceDirectory.DIRECTORY_SEPARATOR, '', $absolutePath);
            $bucket = $this->bucketFor($relativePath);

            preg_match_all('/^use\s+(App\\\\[A-Za-z0-9_\\\\]+);/m', (string) File::get($absolutePath), $matches);

            foreach ($matches[1] as $className) {
                $expectedRelativePath = 'app/'.str_replace('\\', '/', substr($className, strlen('App\\'))).'.php';
                $expectedAbsolutePath = $workspaceDirectory.'/'.$expectedRelativePath;

                if (File::exists($expectedAbsolutePath)) {
                    continue;
                }

                match ($bucket) {
                    'portable_runtime' => $this->recordMissingDependency($portableRuntimeMissingDependencies, $className, $expectedRelativePath, $relativePath),
                    'tests' => $this->recordMissingDependency($testMissingDependencies, $className, $expectedRelativePath, $relativePath),
                    default => $this->recordMissingDependency($referenceMissingDependencies, $className, $expectedRelativePath, $relativePath),
                };
            }
        }

        $portableRuntimeMissingDependencies = $this->sortMissingDependencies($portableRuntimeMissingDependencies);
        $testMissingDependencies = $this->sortMissingDependencies($testMissingDependencies);
        $referenceMissingDependencies = $this->sortMissingDependencies($referenceMissingDependencies);
        $mergedMissingDependencies = $this->mergeMissingDependencies(
            $portableRuntimeMissingDependencies,
            $testMissingDependencies,
            $referenceMissingDependencies,
        );

        return [
            'workspace_directory' => $workspaceDirectory,
            'php_file_count' => $phpFiles->count(),
            'portable_runtime_php_file_count' => $this->countPhpFilesByBucket($phpFiles, $workspaceDirectory, 'portable_runtime'),
            'test_php_file_count' => $this->countPhpFilesByBucket($phpFiles, $workspaceDirectory, 'tests'),
            'reference_php_file_count' => $this->countPhpFilesByBucket($phpFiles, $workspaceDirectory, 'references'),
            'missing_dependency_count' => count($mergedMissingDependencies),
            'portable_runtime_missing_dependency_count' => count($portableRuntimeMissingDependencies),
            'portable_runtime_missing_dependencies' => $portableRuntimeMissingDependencies,
            'test_missing_dependency_count' => count($testMissingDependencies),
            'test_missing_dependencies' => $testMissingDependencies,
            'reference_missing_dependency_count' => count($referenceMissingDependencies),
            'reference_missing_dependencies' => $referenceMissingDependencies,
            'missing_dependencies' => $mergedMissingDependencies,
        ];
    }

    /**
     * @param  array<string, array{expected_path: string, referenced_by: list<string>}>  $missingDependencies
     */
    private function recordMissingDependency(
        array &$missingDependencies,
        string $className,
        string $expectedRelativePath,
        string $relativePath,
    ): void {
        if (! array_key_exists($className, $missingDependencies)) {
            $missingDependencies[$className] = [
                'expected_path' => $expectedRelativePath,
                'referenced_by' => [],
            ];
        }

        $missingDependencies[$className]['referenced_by'][] = $relativePath;
    }

    private function countPhpFilesByBucket(Collection $phpFiles, string $workspaceDirectory, string $bucket): int
    {
        return $phpFiles->filter(function ($file) use ($workspaceDirectory, $bucket): bool {
            $relativePath = str_replace($workspaceDirectory.DIRECTORY_SEPARATOR, '', $file->getPathname());

            return $this->bucketFor($relativePath) === $bucket;
        })->count();
    }

    private function bucketFor(string $relativePath): string
    {
        if (str_starts_with($relativePath, '_integration-references/')) {
            return 'references';
        }

        if (str_starts_with($relativePath, 'tests/')) {
            return 'tests';
        }

        return 'portable_runtime';
    }

    /**
     * @param  array<string, array{expected_path: string, referenced_by: list<string>}>  $missingDependencies
     * @return array<string, array{expected_path: string, referenced_by: list<string>}>
     */
    private function sortMissingDependencies(array $missingDependencies): array
    {
        foreach ($missingDependencies as $className => $details) {
            $missingDependencies[$className]['referenced_by'] = array_values(array_unique($details['referenced_by']));
            sort($missingDependencies[$className]['referenced_by']);
        }

        ksort($missingDependencies);

        return $missingDependencies;
    }

    /**
     * @param  array<string, array{expected_path: string, referenced_by: list<string>}>  ...$groups
     * @return array<string, array{expected_path: string, referenced_by: list<string>}>
     */
    private function mergeMissingDependencies(array ...$groups): array
    {
        $merged = [];

        foreach ($groups as $group) {
            foreach ($group as $className => $details) {
                if (! array_key_exists($className, $merged)) {
                    $merged[$className] = [
                        'expected_path' => $details['expected_path'],
                        'referenced_by' => [],
                    ];
                }

                $merged[$className]['referenced_by'] = array_values(array_unique([
                    ...$merged[$className]['referenced_by'],
                    ...$details['referenced_by'],
                ]));

                sort($merged[$className]['referenced_by']);
            }
        }

        ksort($merged);

        return $merged;
    }
}
