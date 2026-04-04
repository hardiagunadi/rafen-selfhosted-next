<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SelfHostedUpdateManifestService
{
    public function channel(): string
    {
        $channel = trim((string) config('services.self_hosted_update.channel', 'stable'));

        return $channel !== '' ? $channel : 'stable';
    }

    public function repository(): string
    {
        return trim((string) config('services.self_hosted_update.repository', ''));
    }

    public function workdir(): string
    {
        $configured = trim((string) config('services.self_hosted_update.workdir', ''));

        return $configured !== '' ? $configured : base_path();
    }

    public function manifestUrl(): string
    {
        return trim((string) config('services.self_hosted_update.manifest_url', ''));
    }

    public function hasExplicitManifestUrl(): bool
    {
        return $this->manifestUrl() !== '';
    }

    public function canAutoDiscover(): bool
    {
        return $this->parseGitHubRepository() !== null;
    }

    public function configurationStatusMessage(): string
    {
        if ($this->hasExplicitManifestUrl()) {
            return 'Manifest update memakai URL eksplisit dari konfigurasi.';
        }

        if ($this->canAutoDiscover()) {
            return 'Manifest update akan dicari otomatis dari release GitHub terbaru sesuai channel.';
        }

        return 'SELF_HOSTED_UPDATE_MANIFEST_URL belum diisi.';
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(): array
    {
        $url = $this->manifestUrl();

        if ($url !== '') {
            return $this->fetchFromUrl($url);
        }

        $repository = $this->parseGitHubRepository();

        if ($repository === null) {
            throw new RuntimeException('SELF_HOSTED_UPDATE_MANIFEST_URL belum diisi dan SELF_HOSTED_UPDATE_REPOSITORY bukan repo GitHub yang didukung untuk auto-discovery.');
        }

        $releaseResponse = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'rafen-self-hosted-update-checker',
            ])
            ->get(sprintf(
                'https://api.github.com/repos/%s/%s/releases?per_page=10',
                $repository['owner'],
                $repository['name'],
            ));

        if ($releaseResponse->failed()) {
            throw new RuntimeException('Gagal mengambil daftar release GitHub: HTTP '.$releaseResponse->status().' '.$releaseResponse->body());
        }

        $releases = $releaseResponse->json();

        if (! is_array($releases)) {
            throw new RuntimeException('Respons release GitHub tidak valid.');
        }

        $lastError = null;

        foreach ($releases as $release) {
            if (! is_array($release) || (bool) ($release['draft'] ?? false)) {
                continue;
            }

            if ($this->channel() === 'stable' && (bool) ($release['prerelease'] ?? false)) {
                continue;
            }

            $tag = trim((string) ($release['tag_name'] ?? ''));

            if ($tag === '') {
                continue;
            }

            $candidateUrl = $this->resolveManifestUrlFromRelease($release, $repository);

            if ($candidateUrl === null) {
                $lastError = new RuntimeException(sprintf(
                    'Release GitHub %s ditemukan, tetapi asset release-manifest.json belum dipublikasikan.',
                    $tag,
                ));

                continue;
            }

            try {
                $manifest = $this->fetchFromUrl($candidateUrl);
            } catch (RuntimeException $exception) {
                $lastError = $exception;
                continue;
            }

            if (($manifest['release_notes_url'] ?? null) === null) {
                $releaseNotesUrl = trim((string) ($release['html_url'] ?? ''));

                if ($releaseNotesUrl !== '') {
                    $manifest['release_notes_url'] = $releaseNotesUrl;
                }
            }

            if (($manifest['channel'] ?? null) !== $this->channel()) {
                $lastError = new RuntimeException('Release manifest GitHub yang ditemukan belum cocok dengan channel instance.');
                continue;
            }

            return $manifest;
        }

        if ($lastError instanceof RuntimeException) {
            throw new RuntimeException('Auto-discovery release manifest gagal: '.$lastError->getMessage(), 0, $lastError);
        }

        throw new RuntimeException('Auto-discovery release manifest gagal: belum ada release GitHub yang cocok untuk channel '.$this->channel().'.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload, ?string $url = null): array
    {
        $schema = trim((string) ($payload['schema'] ?? ''));
        $channel = trim((string) ($payload['channel'] ?? ''));
        $version = trim((string) ($payload['version'] ?? ''));
        $tag = trim((string) ($payload['tag'] ?? ''));
        $commit = trim((string) ($payload['commit'] ?? ''));
        $publishedAt = $payload['published_at'] ?? null;

        if ($schema !== 'rafen-self-hosted-release:v1') {
            throw new RuntimeException('Manifest update memakai schema yang tidak didukung.');
        }

        if ($channel === '' || $version === '' || $tag === '' || $commit === '' || ! is_string($publishedAt) || trim($publishedAt) === '') {
            throw new RuntimeException('Manifest update wajib berisi channel, version, tag, commit, dan published_at.');
        }

        try {
            $publishedAtIso = Carbon::parse($publishedAt)->toIso8601String();
        } catch (\Throwable) {
            throw new RuntimeException('Field published_at pada manifest update tidak valid.');
        }

        $package = is_array($payload['package'] ?? null) ? $payload['package'] : [];
        $packageRepository = trim((string) ($package['repository'] ?? $this->repository()));
        $packageRef = trim((string) ($package['ref'] ?? $tag));
        $releaseNotesUrl = $payload['release_notes_url'] ?? null;
        $resolvedUrl = $url !== null && trim($url) !== '' ? trim($url) : $this->manifestUrl();

        return [
            'schema' => $schema,
            'channel' => $channel,
            'version' => $version,
            'tag' => $tag,
            'commit' => $commit,
            'published_at' => $publishedAtIso,
            'release_notes_url' => is_string($releaseNotesUrl) && trim($releaseNotesUrl) !== '' ? trim($releaseNotesUrl) : null,
            'requires_maintenance' => (bool) ($payload['requires_maintenance'] ?? false),
            'requires_backup' => (bool) ($payload['requires_backup'] ?? false),
            'requires_migration' => (bool) ($payload['requires_migration'] ?? false),
            'package' => [
                'type' => trim((string) ($package['type'] ?? 'git-tag')),
                'repository' => $packageRepository !== '' ? $packageRepository : null,
                'ref' => $packageRef !== '' ? $packageRef : $tag,
            ],
            'manifest_url' => $resolvedUrl !== '' ? $resolvedUrl : null,
            'raw' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFromUrl(string $url): array
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil manifest update: HTTP '.$response->status().' '.$response->body());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Manifest update tidak valid: payload JSON harus berupa object.');
        }

        return $this->normalizePayload($payload, $url);
    }

    /**
     * @param  array<string, mixed>  $release
     * @param  array{owner: string, name: string}  $repository
     */
    private function resolveManifestUrlFromRelease(array $release, array $repository): ?string
    {
        $assets = $release['assets'] ?? null;

        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                if (trim((string) ($asset['name'] ?? '')) !== 'release-manifest.json') {
                    continue;
                }

                $browserDownloadUrl = trim((string) ($asset['browser_download_url'] ?? ''));

                return $browserDownloadUrl !== '' ? $browserDownloadUrl : null;
            }

            return null;
        }

        $tag = trim((string) ($release['tag_name'] ?? ''));

        if ($tag === '') {
            return null;
        }

        return sprintf(
            'https://github.com/%s/%s/releases/download/%s/release-manifest.json',
            $repository['owner'],
            $repository['name'],
            $tag,
        );
    }

    /**
     * @return array{owner: string, name: string}|null
     */
    private function parseGitHubRepository(): ?array
    {
        $repository = $this->repository();

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
