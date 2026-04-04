<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SelfHostedReleaseManifestService
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function build(array $overrides = []): array
    {
        $tag = $this->resolveTag($overrides);
        $version = $this->resolveVersion($tag, $overrides);
        $commit = $this->resolveCommit($overrides);
        $publishedAt = $this->resolvePublishedAt($overrides);
        $channel = trim((string) ($overrides['channel'] ?? config('services.self_hosted_update.channel', 'stable')));
        $repository = trim((string) ($overrides['repository'] ?? config('services.self_hosted_update.repository', '')));
        $releaseNotesUrl = $this->resolveReleaseNotesUrl($repository, $tag, $overrides);
        $postUpdateArtisan = $this->resolvePostUpdateArtisan($overrides);

        $payload = [
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => $channel !== '' ? $channel : 'stable',
            'version' => $version,
            'tag' => $tag,
            'commit' => $commit,
            'published_at' => $publishedAt,
            'release_notes_url' => $releaseNotesUrl,
            'severity' => trim((string) ($overrides['severity'] ?? 'normal')) ?: 'normal',
            'requires_maintenance' => (bool) ($overrides['requires_maintenance'] ?? true),
            'requires_backup' => (bool) ($overrides['requires_backup'] ?? true),
            'requires_migration' => (bool) ($overrides['requires_migration'] ?? true),
            'package' => [
                'type' => 'git-tag',
                'repository' => $repository !== '' ? $repository : null,
                'ref' => trim((string) ($overrides['package_ref'] ?? $tag)) ?: $tag,
            ],
            'post_update' => [
                'artisan' => $postUpdateArtisan,
            ],
        ];

        $minimumSupportedFrom = trim((string) ($overrides['minimum_supported_from'] ?? ''));

        if ($minimumSupportedFrom !== '') {
            $payload['minimum_supported_from'] = $minimumSupportedFrom;
        }

        $phpVersion = trim((string) ($overrides['php_version'] ?? $this->detectPhpVersion()));

        if ($phpVersion !== '') {
            $payload['php_version'] = $phpVersion;
        }

        $nodeMajor = $this->resolveNodeMajor($overrides);

        if ($nodeMajor !== null) {
            $payload['node_major'] = $nodeMajor;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function write(string $destinationPath, array $overrides = []): array
    {
        File::ensureDirectoryExists(dirname($destinationPath));

        $payload = $this->build($overrides);

        File::put($destinationPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveTag(array $overrides): string
    {
        $tag = trim((string) ($overrides['tag'] ?? ''));

        if ($tag !== '') {
            return $tag;
        }

        $githubRefName = trim((string) env('GITHUB_REF_NAME', ''));

        if ($githubRefName !== '') {
            return $githubRefName;
        }

        $gitTag = $this->runGitCommand('git describe --tags --exact-match HEAD');

        if ($gitTag !== null) {
            return $gitTag;
        }

        throw new RuntimeException('Tag release tidak ditemukan. Berikan --tag atau jalankan command pada commit yang sudah ditandai git tag.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveVersion(string $tag, array $overrides): string
    {
        $version = trim((string) ($overrides['version'] ?? ''));

        if ($version !== '') {
            return $version;
        }

        if (preg_match('/^v(.+)$/', $tag, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        $configuredVersion = trim((string) config('app.version', ''));

        if ($configuredVersion !== '' && $configuredVersion !== 'main-dev') {
            return $configuredVersion;
        }

        throw new RuntimeException('Versi release tidak dapat ditentukan. Berikan --version atau gunakan tag dengan format v<version>.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveCommit(array $overrides): string
    {
        $commit = trim((string) ($overrides['commit'] ?? ''));

        if ($commit !== '') {
            return $commit;
        }

        $configuredCommit = trim((string) config('app.commit', ''));

        if ($configuredCommit !== '') {
            return $configuredCommit;
        }

        $gitCommit = $this->runGitCommand('git rev-parse --short HEAD');

        if ($gitCommit !== null) {
            return $gitCommit;
        }

        throw new RuntimeException('Commit release tidak dapat ditentukan. Berikan --commit atau pastikan repo git tersedia.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolvePublishedAt(array $overrides): string
    {
        $publishedAt = trim((string) ($overrides['published_at'] ?? ''));

        if ($publishedAt === '') {
            $publishedAt = now()->toIso8601String();
        }

        try {
            return Carbon::parse($publishedAt)->toIso8601String();
        } catch (\Throwable) {
            throw new RuntimeException('Nilai published_at tidak valid. Gunakan format ISO-8601.');
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveReleaseNotesUrl(string $repository, string $tag, array $overrides): ?string
    {
        $releaseNotesUrl = trim((string) ($overrides['release_notes_url'] ?? ''));

        if ($releaseNotesUrl !== '') {
            return $releaseNotesUrl;
        }

        $parsedRepository = $this->parseGitHubRepository($repository);

        if ($parsedRepository === null) {
            return null;
        }

        return sprintf(
            'https://github.com/%s/%s/releases/tag/%s',
            $parsedRepository['owner'],
            $parsedRepository['name'],
            $tag,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return list<string>
     */
    private function resolvePostUpdateArtisan(array $overrides): array
    {
        $commands = $overrides['post_update_artisan'] ?? null;

        if (is_array($commands) && $commands !== []) {
            $normalized = array_values(array_filter(array_map(function ($command): ?string {
                if (! is_string($command)) {
                    return null;
                }

                $trimmed = trim($command);

                if ($trimmed === '') {
                    return null;
                }

                if (str_starts_with($trimmed, 'php artisan ')) {
                    $trimmed = trim(substr($trimmed, strlen('php artisan ')));
                }

                return 'php artisan '.$trimmed;
            }, $commands)));

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [
            'php artisan optimize:clear',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveNodeMajor(array $overrides): ?int
    {
        $nodeMajor = $overrides['node_major'] ?? null;

        if ($nodeMajor !== null && $nodeMajor !== '') {
            return max(0, (int) $nodeMajor);
        }

        $detected = $this->runShellCommand('node --version');

        if ($detected === null) {
            return null;
        }

        if (preg_match('/^v?(\d+)/', $detected, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function detectPhpVersion(): string
    {
        return PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    }

    private function runGitCommand(string $command): ?string
    {
        return $this->runShellCommand($command);
    }

    private function runShellCommand(string $command): ?string
    {
        $result = Process::path(base_path())
            ->timeout(10)
            ->run($command);

        if (! $result->successful()) {
            return null;
        }

        $output = trim($result->output());

        return $output !== '' ? $output : null;
    }

    /**
     * @return array{owner: string, name: string}|null
     */
    private function parseGitHubRepository(string $repository): ?array
    {
        $repository = trim($repository);

        if ($repository === '') {
            return null;
        }

        if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#', $repository, $matches) !== 1) {
            return null;
        }

        $owner = trim($matches[1]);
        $name = trim($matches[2]);

        if ($owner === '' || $name === '') {
            return null;
        }

        return [
            'owner' => $owner,
            'name' => $name,
        ];
    }
}
