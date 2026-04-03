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

    /**
     * @return array<string, mixed>
     */
    public function fetch(): array
    {
        $url = $this->manifestUrl();

        if ($url === '') {
            throw new RuntimeException('SELF_HOSTED_UPDATE_MANIFEST_URL belum diisi.');
        }

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
}
