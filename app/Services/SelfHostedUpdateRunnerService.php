<?php

namespace App\Services;

use App\Models\SelfHostedUpdateRun;
use App\Models\SelfHostedUpdateState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SelfHostedUpdateRunnerService
{
    private const int OUTPUT_LIMIT_CHARS = 20000;

    /**
     * @var list<string>
     */
    private const array GENERATED_STATUS_IGNORE_PREFIXES = [
        'storage/framework/self-hosted-',
        'storage/framework/rafen-selfhosted-',
    ];

    public function __construct(
        private readonly AppBuildMetadataService $buildMetadataService,
        private readonly SelfHostedUpdateManifestService $manifestService,
        private readonly SelfHostedUpdateStatusService $statusService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function apply(
        ?string $target = null,
        bool $dryRun = false,
        bool $skipBackup = false,
        ?int $triggeredByUserId = null,
    ): array {
        $lock = Cache::lock('self-hosted-update:apply:'.$this->manifestService->channel(), 1800);

        if (! $lock->get()) {
            return [
                'status' => 'failed',
                'message' => 'Proses update lain sedang berjalan. Tunggu sampai selesai lalu coba lagi.',
                'run_id' => null,
            ];
        }

        try {
            return $this->runApply($target, $dryRun, $skipBackup, $triggeredByUserId);
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SelfHostedUpdateRun>
     */
    public function recentRuns(int $limit = 5)
    {
        if (! Schema::hasTable('self_hosted_update_runs')) {
            return SelfHostedUpdateRun::newCollection();
        }

        return SelfHostedUpdateRun::query()
            ->where('channel', $this->manifestService->channel())
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function runApply(
        ?string $target,
        bool $dryRun,
        bool $skipBackup,
        ?int $triggeredByUserId,
    ): array {
        $this->ensureStorageReady();

        try {
            $snapshot = $this->statusService->check();
            $manifest = $this->manifestService->normalizePayload(
                $this->manifestPayloadFromSnapshot($snapshot),
                $snapshot['latest_manifest_url'] ?? $snapshot['manifest_url'] ?? null,
            );
            $targetRelease = $this->resolveTargetRelease($snapshot, $manifest, $target);
            $preflight = $this->preflight($snapshot, $targetRelease, $skipBackup);
        } catch (RuntimeException $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'run_id' => null,
            ];
        }

        $run = SelfHostedUpdateRun::query()->create([
            'channel' => $snapshot['channel'],
            'action' => $dryRun ? 'preflight' : 'apply',
            'target_version' => $targetRelease['version'],
            'target_ref' => $targetRelease['ref'],
            'target_commit' => $targetRelease['commit'],
            'current_version' => $snapshot['current_version'],
            'current_commit' => $snapshot['current_commit'],
            'status' => 'running',
            'started_at' => now(),
            'triggered_by_user_id' => $triggeredByUserId,
            'metadata' => [
                'dry_run' => $dryRun,
                'skip_backup' => $skipBackup,
                'workdir' => $this->manifestService->workdir(),
                'preflight' => $preflight,
            ],
        ]);

        $log = [
            sprintf('Target update: %s (%s)', $targetRelease['version'], $targetRelease['ref']),
            'Workdir: '.$this->manifestService->workdir(),
        ];
        $rollbackRef = $preflight['current_head'] ?? null;
        $backupPath = null;
        $maintenanceEnabled = false;
        $finalStatus = 'failed';
        $finalMessage = 'Update gagal dijalankan.';
        $finalMetadata = [
            'dry_run' => $dryRun,
            'skip_backup' => $skipBackup,
            'preflight' => $preflight,
        ];

        if ($dryRun) {
            $log[] = 'Dry-run selesai. Tidak ada perubahan file, dependency, atau database.';
            $finalStatus = 'dry_run';
            $finalMessage = 'Simulasi apply selesai. Gunakan command CLI untuk menjalankan update aktual.';

            return $this->finishRun($run, $snapshot, $targetRelease, $finalStatus, $finalMessage, $log, null, $rollbackRef, $finalMetadata);
        }

        try {
            if ($targetRelease['requires_maintenance']) {
                $maintenanceEnabled = true;
                $this->runCommand(
                    sprintf('%s artisan down --retry=60', $this->phpBinary()),
                    'maintenance_down',
                    $log,
                    60,
                );
            } else {
                $log[] = 'Maintenance mode tidak diperlukan oleh manifest release ini.';
            }

            if ($preflight['backup_required'] ?? false) {
                $backupPath = $this->createBackup($log);
            } else {
                $log[] = 'Backup dilewati sesuai manifest/opsi.';
            }

            $this->runCommand('git fetch --tags origin', 'git_fetch', $log, 300);
            $this->runCommand(
                sprintf('git checkout --detach %s', escapeshellarg($targetRelease['ref'])),
                'git_checkout',
                $log,
                300,
            );
            $metadata = $this->buildMetadataService->syncEnvFile(
                $this->manifestService->workdir().'/.env',
                $targetRelease['version'],
                $targetRelease['commit'],
            );
            $log[] = sprintf(
                'Metadata build lokal disinkronkan ke %s (APP_VERSION=%s, APP_COMMIT=%s).',
                $metadata['env_path'],
                $metadata['version'],
                $metadata['commit'] ?? '-',
            );
            $this->runCommand(
                'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader',
                'composer_install',
                $log,
                1800,
            );

            if ($targetRelease['requires_migration']) {
                $this->runCommand(
                    sprintf('%s artisan migrate --force', $this->phpBinary()),
                    'artisan_migrate',
                    $log,
                    900,
                );
            }

            foreach ($targetRelease['post_update_artisan'] as $artisanCommand) {
                $this->runCommand(
                    sprintf('%s artisan %s', $this->phpBinary(), $artisanCommand),
                    'artisan_'.str_replace([':', ' '], ['_', '_'], $artisanCommand),
                    $log,
                    300,
                );
            }

            $log[] = 'Update berhasil diterapkan ke target release.';
            $finalStatus = 'success';
            $finalMessage = 'Update berhasil diterapkan. Review health check dan service penting sesudah deploy.';
        } catch (RuntimeException $exception) {
            $log[] = 'Apply gagal: '.$exception->getMessage();
            $finalStatus = 'failed';
            $finalMessage = $exception->getMessage();

            if ($rollbackRef !== null && $rollbackRef !== '') {
                $rollbackResult = $this->attemptRollback($rollbackRef, $log);
                $log[] = $rollbackResult
                    ? 'Rollback code berhasil dijalankan ke ref sebelumnya.'
                    : 'Rollback code gagal dijalankan otomatis. Gunakan rollback_ref yang tersimpan untuk recovery manual.';
            }
        } finally {
            if ($maintenanceEnabled) {
                $result = Process::path($this->manifestService->workdir())
                    ->timeout(60)
                    ->run(sprintf('%s artisan up', $this->phpBinary()));

                if ($result->successful()) {
                    $log[] = 'Maintenance mode dinonaktifkan.';
                } else {
                    $log[] = 'Gagal menonaktifkan maintenance mode: '.$this->formatProcessFailure($result->output(), $result->errorOutput(), $result->exitCode());
                }
            }
        }

        return $this->finishRun(
            $run,
            $snapshot,
            $targetRelease,
            $finalStatus,
            $finalMessage,
            $log,
            $backupPath,
            $rollbackRef,
            $finalMetadata,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $targetRelease
     * @return array<string, mixed>
     */
    private function preflight(array $snapshot, array $targetRelease, bool $skipBackup): array
    {
        $checks = [];
        $workdir = $this->manifestService->workdir();

        $this->assertCheck(is_dir($workdir), 'workdir', 'Workdir update ditemukan.', 'SELF_HOSTED_UPDATE_WORKDIR tidak valid.', $checks);
        $this->assertCheck(is_dir($workdir.'/.git'), 'git_repo', 'Repository Git terdeteksi.', 'Workdir update bukan repository Git.', $checks);

        try {
            DB::connection()->getPdo();
            $this->assertCheck(true, 'database', 'Koneksi database aktif.', 'Koneksi database gagal.', $checks);
        } catch (Throwable $exception) {
            $this->assertCheck(false, 'database', 'Koneksi database aktif.', 'Koneksi database gagal: '.$exception->getMessage(), $checks);
        }

        $currentHead = $this->readCurrentHead();
        $this->assertCheck($currentHead !== null, 'current_head', 'Current HEAD berhasil dibaca.', 'Tidak bisa membaca current HEAD repository.', $checks);

        $dirtyEntries = $this->dirtyWorktreeEntries();
        $ignoreDirty = (bool) config('services.self_hosted_update.ignore_dirty_worktree', false);

        if ($ignoreDirty) {
            $checks[] = [
                'key' => 'worktree',
                'status' => 'warning',
                'message' => 'Pemeriksaan dirty worktree dilewati oleh konfigurasi.',
            ];
        } elseif ($dirtyEntries === []) {
            $checks[] = [
                'key' => 'worktree',
                'status' => 'ok',
                'message' => 'Worktree bersih.',
            ];
        } else {
            $checks[] = [
                'key' => 'worktree',
                'status' => 'failed',
                'message' => 'Worktree masih kotor: '.implode(', ', array_slice($dirtyEntries, 0, 5)),
            ];
        }

        $backupRequired = (bool) $targetRelease['requires_backup'] && ! $skipBackup;

        if ($backupRequired) {
            $this->assertCheck($this->canPrepareBackup(), 'backup', 'Direktori backup siap dipakai.', 'Backup tidak siap. Periksa driver database dan izin storage.', $checks);
        } elseif ((bool) $targetRelease['requires_backup']) {
            $checks[] = [
                'key' => 'backup',
                'status' => 'warning',
                'message' => 'Manifest meminta backup, tetapi opsi skip backup dipakai.',
            ];
        } else {
            $checks[] = [
                'key' => 'backup',
                'status' => 'ok',
                'message' => 'Backup tidak diwajibkan oleh manifest ini.',
            ];
        }

        $failedChecks = collect($checks)
            ->filter(fn (array $check): bool => $check['status'] === 'failed')
            ->pluck('message')
            ->all();

        if ($failedChecks !== []) {
            throw new RuntimeException('Preflight update gagal: '.implode(' | ', $failedChecks));
        }

        return [
            'checks' => $checks,
            'current_head' => $currentHead,
            'current_ref' => $snapshot['current_ref'] ?? null,
            'target_ref' => $targetRelease['ref'],
            'target_version' => $targetRelease['version'],
            'backup_required' => $backupRequired,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function resolveTargetRelease(array $snapshot, array $manifest, ?string $target): array
    {
        $targetRef = (string) data_get($manifest, 'package.ref', $manifest['tag']);
        $targetCandidates = array_filter([
            $manifest['tag'] ?? null,
            $manifest['version'] ?? null,
            $manifest['commit'] ?? null,
            $targetRef,
        ]);

        if ($target !== null && $target !== '' && ! in_array($target, $targetCandidates, true)) {
            throw new RuntimeException('Target ref yang diminta belum cocok dengan manifest aktif. Jalankan check update lagi atau pakai ref/tag yang ada di manifest terbaru.');
        }

        if (($snapshot['update_available'] ?? false) !== true && ($target === null || $target === '')) {
            throw new RuntimeException('Instance ini sudah menggunakan release terbaru. Tidak ada update yang perlu diterapkan.');
        }

        $currentFingerprint = implode('|', [
            $snapshot['current_version'] ?? '',
            $snapshot['current_commit'] ?? '',
            $snapshot['current_ref'] ?? '',
        ]);
        $targetFingerprint = implode('|', [
            $manifest['version'] ?? '',
            $manifest['commit'] ?? '',
            $targetRef,
        ]);

        if ($currentFingerprint === $targetFingerprint) {
            throw new RuntimeException('Target release sudah sama dengan versi yang sedang terpasang.');
        }

        $postUpdateArtisan = data_get($manifest, 'raw.post_update.artisan');
        $postUpdateArtisan = is_array($postUpdateArtisan) && $postUpdateArtisan !== []
            ? array_values(array_filter(array_map(
                fn ($command) => $this->normalizeArtisanCommand($command),
                $postUpdateArtisan
            )))
            : ['optimize:clear', 'config:cache', 'route:cache', 'view:cache'];

        return [
            'version' => (string) $manifest['version'],
            'commit' => (string) $manifest['commit'],
            'ref' => $targetRef,
            'repository' => data_get($manifest, 'package.repository'),
            'requires_backup' => (bool) ($manifest['requires_backup'] ?? false),
            'requires_maintenance' => (bool) ($manifest['requires_maintenance'] ?? false),
            'requires_migration' => (bool) ($manifest['requires_migration'] ?? false),
            'post_update_artisan' => $postUpdateArtisan,
        ];
    }

    private function ensureStorageReady(): void
    {
        if (! Schema::hasTable('self_hosted_update_states')) {
            throw new RuntimeException('Tabel self_hosted_update_states belum tersedia. Jalankan php artisan migrate --force terlebih dahulu.');
        }

        if (! Schema::hasTable('self_hosted_update_runs')) {
            throw new RuntimeException('Tabel self_hosted_update_runs belum tersedia. Jalankan php artisan migrate --force terlebih dahulu.');
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $targetRelease
     * @param  list<string>  $log
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function finishRun(
        SelfHostedUpdateRun $run,
        array $snapshot,
        array $targetRelease,
        string $status,
        string $message,
        array $log,
        ?string $backupPath,
        ?string $rollbackRef,
        array $metadata,
    ): array {
        $outputExcerpt = $this->truncateOutput(implode(PHP_EOL, $log));

        $run->forceFill([
            'status' => $status,
            'finished_at' => now(),
            'backup_path' => $backupPath,
            'rollback_ref' => $rollbackRef,
            'output_excerpt' => $outputExcerpt,
            'metadata' => array_merge($run->metadata ?? [], $metadata),
        ])->save();

        $this->syncStateAfterRun($snapshot, $targetRelease, $status, $message, $rollbackRef);

        return [
            'status' => $status,
            'message' => $message,
            'run_id' => $run->id,
            'target_version' => $targetRelease['version'],
            'target_ref' => $targetRelease['ref'],
            'rollback_ref' => $rollbackRef,
            'backup_path' => $backupPath,
            'output_excerpt' => $outputExcerpt,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $targetRelease
     */
    private function syncStateAfterRun(
        array $snapshot,
        array $targetRelease,
        string $status,
        string $message,
        ?string $rollbackRef,
    ): void {
        $state = SelfHostedUpdateState::query()->firstOrNew([
            'channel' => (string) ($snapshot['channel'] ?? $this->manifestService->channel()),
        ]);

        $attributes = [
            'rollback_ref' => $rollbackRef,
        ];

        if ($status !== 'dry_run') {
            $attributes['last_applied_at'] = now();
            $attributes['last_apply_status'] = $status;
            $attributes['last_apply_message'] = $message;
        }

        if ($status === 'success') {
            $attributes['current_version'] = $targetRelease['version'];
            $attributes['current_commit'] = $targetRelease['commit'];
            $attributes['current_ref'] = $targetRelease['ref'];
            $attributes['update_available'] = false;
        }

        $state->fill($attributes)->save();
    }

    /**
     * @param  list<string>  $log
     */
    private function runCommand(string $command, string $step, array &$log, int $timeout = 300): void
    {
        $log[] = sprintf('[%s] %s', $step, $command);

        $result = Process::path($this->manifestService->workdir())
            ->timeout($timeout)
            ->run($command);

        $output = trim($result->output());
        $errorOutput = trim($result->errorOutput());

        if ($output !== '') {
            $log[] = $output;
        }

        if ($errorOutput !== '') {
            $log[] = $errorOutput;
        }

        if (! $result->successful()) {
            throw new RuntimeException($this->formatProcessFailure($output, $errorOutput, $result->exitCode(), $step));
        }
    }

    /**
     * @param  list<string>  $log
     */
    private function createBackup(array &$log): string
    {
        $driver = (string) config('database.default', 'sqlite');
        $connection = config("database.connections.{$driver}", []);
        $backupDir = storage_path('app/self-hosted-updates/backups');

        File::ensureDirectoryExists($backupDir);

        if (! is_writable($backupDir)) {
            throw new RuntimeException('Direktori backup tidak dapat ditulisi: '.$backupDir);
        }

        if ($driver === 'sqlite') {
            $databasePath = (string) ($connection['database'] ?? '');

            if ($databasePath === '' || $databasePath === ':memory:') {
                throw new RuntimeException('Backup sqlite tidak didukung untuk database in-memory.');
            }

            $targetPath = $backupDir.'/backup_'.now()->format('Ymd_His').'.sqlite';
            File::copy($databasePath, $targetPath);
            $log[] = 'Backup sqlite dibuat: '.$targetPath;

            return $targetPath;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Driver database '.$driver.' belum didukung untuk backup otomatis.');
        }

        $filename = 'backup_'.now()->format('Ymd_His').'.sql.gz';
        $path = $backupDir.'/'.$filename;
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');
        $socket = trim((string) ($connection['unix_socket'] ?? ''));

        $hostSegment = $socket !== ''
            ? '--socket='.escapeshellarg($socket)
            : '--host='.escapeshellarg($host).' --port='.escapeshellarg($port);

        $command = sprintf(
            'mysqldump %s --user=%s --password=%s --single-transaction --routines %s | gzip > %s',
            $hostSegment,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($path),
        );

        $this->runCommand($command, 'database_backup', $log, 1800);

        if (! File::exists($path) || (int) File::size($path) === 0) {
            throw new RuntimeException('Backup database gagal dibuat.');
        }

        $log[] = 'Backup database dibuat: '.$path;

        return $path;
    }

    /**
     * @param  list<string>  $log
     */
    private function attemptRollback(string $rollbackRef, array &$log): bool
    {
        $commands = [
            sprintf('git checkout --detach %s', escapeshellarg($rollbackRef)),
            sprintf('%s artisan optimize:clear', $this->phpBinary()),
        ];

        foreach ($commands as $command) {
            $result = Process::path($this->manifestService->workdir())
                ->timeout(300)
                ->run($command);

            $output = trim($result->output().' '.$result->errorOutput());

            if ($output !== '') {
                $log[] = $output;
            }

            if (! $result->successful()) {
                return false;
            }
        }

        return true;
    }

    private function canPrepareBackup(): bool
    {
        try {
            $backupDir = storage_path('app/self-hosted-updates/backups');
            File::ensureDirectoryExists($backupDir);

            if (! is_writable($backupDir)) {
                return false;
            }

            $driver = (string) config('database.default', 'sqlite');
            $connection = config("database.connections.{$driver}", []);

            return match ($driver) {
                'sqlite' => ($connection['database'] ?? null) !== ':memory:' && is_string($connection['database'] ?? null) && trim((string) $connection['database']) !== '',
                'mysql', 'mariadb' => trim((string) ($connection['database'] ?? '')) !== '',
                default => false,
            };
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function dirtyWorktreeEntries(): array
    {
        $result = Process::path($this->manifestService->workdir())
            ->timeout(15)
            ->run('git status --short --untracked-files=normal');

        if (! $result->successful()) {
            return ['git status gagal dijalankan'];
        }

        return collect(preg_split('/\r\n|\r|\n/', trim($result->output())) ?: [])
            ->filter(fn ($line) => is_string($line) && trim($line) !== '')
            ->map(fn ($line) => trim((string) $line))
            ->reject(function (string $line): bool {
                $path = trim(substr($line, 3));

                foreach (self::GENERATED_STATUS_IGNORE_PREFIXES as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    private function readCurrentHead(): ?string
    {
        $result = Process::path($this->manifestService->workdir())
            ->timeout(15)
            ->run('git rev-parse HEAD');

        if (! $result->successful()) {
            return null;
        }

        $head = trim($result->output());

        return $head !== '' ? $head : null;
    }

    private function phpBinary(): string
    {
        return escapeshellarg(PHP_BINARY);
    }

    private function normalizeArtisanCommand(mixed $command): string
    {
        if (! is_string($command)) {
            return '';
        }

        $normalized = trim($command);

        if ($normalized === '') {
            return '';
        }

        return preg_replace('/^(php\s+artisan\s+)/i', '', $normalized, 1) ?? $normalized;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function manifestPayloadFromSnapshot(array $snapshot): array
    {
        $payload = $snapshot['manifest_payload'] ?? null;

        if (! is_array($payload)) {
            throw new RuntimeException('Manifest update terbaru belum tersedia. Jalankan cek update lagi.');
        }

        return $payload;
    }

    /**
     * @param  list<array<string, string>>  $checks
     */
    private function assertCheck(bool $passed, string $key, string $successMessage, string $failureMessage, array &$checks): void
    {
        $checks[] = [
            'key' => $key,
            'status' => $passed ? 'ok' : 'failed',
            'message' => $passed ? $successMessage : $failureMessage,
        ];
    }

    private function truncateOutput(string $output): string
    {
        if (mb_strlen($output) <= self::OUTPUT_LIMIT_CHARS) {
            return $output;
        }

        return mb_substr($output, 0, self::OUTPUT_LIMIT_CHARS).'... [truncated]';
    }

    private function formatProcessFailure(string $output, string $errorOutput, ?int $exitCode, ?string $step = null): string
    {
        $headline = $step !== null ? 'Langkah '.$step.' gagal' : 'Perintah gagal';
        $details = array_values(array_filter([
            $exitCode !== null ? 'exit code '.$exitCode : null,
            trim($errorOutput) !== '' ? trim($errorOutput) : null,
            trim($output) !== '' ? trim($output) : null,
        ]));

        if ($details === []) {
            return $headline;
        }

        return $headline.': '.implode(' | ', $details);
    }
}
