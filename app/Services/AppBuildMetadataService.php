<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class AppBuildMetadataService
{
    /**
     * @return array{version: string, commit: string|null, tag: string|null}
     */
    public function detect(?string $workdir = null): array
    {
        $workdir ??= base_path();

        $tag = $this->runGitCommand($workdir, 'git describe --tags --exact-match HEAD');
        $commit = trim((string) config('app.commit', ''));

        if ($commit === '') {
            $commit = $this->runGitCommand($workdir, 'git rev-parse --short HEAD') ?? '';
        }

        $version = trim((string) config('app.version', 'main-dev'));

        if (is_string($tag) && preg_match('/^v(.+)/', $tag, $matches) === 1) {
            $version = trim((string) $matches[1]);
        } elseif ($version === '') {
            $version = 'main-dev';
        }

        return [
            'version' => $version !== '' ? $version : 'main-dev',
            'commit' => $commit !== '' ? $commit : null,
            'tag' => $tag,
        ];
    }

    /**
     * @return array{env_path: string, version: string, commit: string|null}
     */
    public function syncEnvFile(string $envPath, ?string $version = null, ?string $commit = null): array
    {
        $resolvedVersion = trim((string) ($version ?? ''));
        $resolvedCommit = trim((string) ($commit ?? ''));

        if ($resolvedVersion === '' || $resolvedCommit === '') {
            $detected = $this->detect(dirname($envPath));
        } else {
            $detected = [
                'version' => $resolvedVersion,
                'commit' => $resolvedCommit,
                'tag' => null,
            ];
        }

        if ($resolvedVersion === '') {
            $resolvedVersion = $detected['version'];
        }

        if ($resolvedCommit === '') {
            $resolvedCommit = (string) ($detected['commit'] ?? '');
        }

        $env = File::exists($envPath) ? (string) File::get($envPath) : '';

        $env = $this->upsertEnvValue($env, 'APP_VERSION', $resolvedVersion);
        $env = $this->upsertEnvValue($env, 'APP_COMMIT', $resolvedCommit);

        if ($env !== '' && ! str_ends_with($env, PHP_EOL)) {
            $env .= PHP_EOL;
        }

        File::put($envPath, $env);

        return [
            'env_path' => $envPath,
            'version' => $resolvedVersion,
            'commit' => $resolvedCommit !== '' ? $resolvedCommit : null,
        ];
    }

    private function upsertEnvValue(string $env, string $key, string $value): string
    {
        $formattedValue = $this->formatEnvValue($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $line = $key.'='.$formattedValue;

        if (preg_match($pattern, $env) === 1) {
            return (string) preg_replace($pattern, $line, $env);
        }

        if ($env !== '' && ! str_ends_with($env, PHP_EOL)) {
            $env .= PHP_EOL;
        }

        return $env.$line.PHP_EOL;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $needsQuoting = preg_match('/[\s#"\'\\\\]/', $value) === 1;

        if (! $needsQuoting) {
            return $value;
        }

        return '"'.str_replace('"', '\\"', $value).'"';
    }

    private function runGitCommand(string $workdir, string $command): ?string
    {
        $result = Process::path($workdir)
            ->timeout(10)
            ->run($command);

        if (! $result->successful()) {
            return null;
        }

        $output = trim($result->output());

        return $output !== '' ? $output : null;
    }
}
