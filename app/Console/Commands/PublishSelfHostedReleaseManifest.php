<?php

namespace App\Console\Commands;

use App\Services\SelfHostedReleaseManifestService;
use Illuminate\Console\Command;
use RuntimeException;

class PublishSelfHostedReleaseManifest extends Command
{
    protected $signature = 'self-hosted:publish-release-manifest
        {path? : Lokasi output file release-manifest.json}
        {--tag= : Git tag release, misalnya v2026.04.03-main.2}
        {--release-version= : Nomor versi release tanpa prefix v}
        {--commit= : Commit SHA pendek atau penuh untuk release ini}
        {--published-at= : Waktu publish release dalam format ISO-8601}
        {--release-notes-url= : URL release notes}
        {--channel=stable : Channel manifest yang dipublikasikan}
        {--repository= : URL repository package self-hosted}
        {--minimum-supported-from= : Versi minimum yang boleh di-upgrade}
        {--php-version= : Versi PHP minimum yang disarankan}
        {--node-major= : Versi major Node.js yang disarankan}
        {--severity=normal : Severity release, misalnya normal atau warning}
        {--without-maintenance : Tandai bahwa maintenance mode tidak diperlukan}
        {--without-backup : Tandai bahwa backup tidak diwajibkan}
        {--without-migration : Tandai bahwa migrasi tidak diwajibkan}
        {--post-update=* : Daftar command artisan pasca-update}
        {--json : Tampilkan payload manifest sebagai JSON}';

    protected $description = 'Buat file release-manifest.json untuk release self-hosted dan siapkan sebagai asset GitHub Release.';

    public function handle(SelfHostedReleaseManifestService $manifestService): int
    {
        $path = (string) ($this->argument('path') ?: base_path('release-manifest.json'));

        try {
            $payload = $manifestService->write($path, [
                'tag' => $this->option('tag'),
                'version' => $this->option('release-version'),
                'commit' => $this->option('commit'),
                'published_at' => $this->option('published-at'),
                'release_notes_url' => $this->option('release-notes-url'),
                'channel' => $this->option('channel'),
                'repository' => $this->option('repository'),
                'minimum_supported_from' => $this->option('minimum-supported-from'),
                'php_version' => $this->option('php-version'),
                'node_major' => $this->option('node-major'),
                'severity' => $this->option('severity'),
                'requires_maintenance' => ! (bool) $this->option('without-maintenance'),
                'requires_backup' => ! (bool) $this->option('without-backup'),
                'requires_migration' => ! (bool) $this->option('without-migration'),
                'post_update_artisan' => $this->option('post-update'),
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Release manifest self-hosted berhasil dipublikasikan.');
        $this->line('Output Path       : '.$path);
        $this->line('Channel           : '.($payload['channel'] ?? '-'));
        $this->line('Version           : '.($payload['version'] ?? '-'));
        $this->line('Tag               : '.($payload['tag'] ?? '-'));
        $this->line('Commit            : '.($payload['commit'] ?? '-'));

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
