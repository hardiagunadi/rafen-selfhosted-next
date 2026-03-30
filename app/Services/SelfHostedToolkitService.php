<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class SelfHostedToolkitService
{
    private const int OUTPUT_LIMIT_CHARS = 20000;

    /**
     * @var list<string>
     */
    private const array GENERATED_STATUS_IGNORE_PREFIXES = [
        'storage/framework/self-hosted-',
        'storage/framework/rafen-selfhosted-',
    ];

    /**
     * @return array<string, array{label: string, command: string, note: string, artifact_path: string|null, tone: string}>
     */
    public static function definitions(): array
    {
        return [
            'manifest' => [
                'label' => 'Manifest',
                'command' => 'php artisan self-hosted:manifest --json',
                'note' => 'Review daftar file dan touchpoint cluster self-hosted.',
                'artifact_path' => null,
                'tone' => 'info',
                'requires_clean_worktree' => false,
            ],
            'cutover_plan' => [
                'label' => 'Cutover Plan',
                'command' => 'php artisan self-hosted:cutover-plan --json',
                'note' => 'Lihat runbook cutover sebelum bundle dipublikasikan.',
                'artifact_path' => null,
                'tone' => 'secondary',
                'requires_clean_worktree' => false,
            ],
            'stage' => [
                'label' => 'Stage Bundle',
                'command' => 'php artisan self-hosted:stage storage/framework/self-hosted-stage-ui --force',
                'note' => 'Buat bundle staging self-hosted ke direktori kerja internal.',
                'artifact_path' => 'storage/framework/self-hosted-stage-ui',
                'tone' => 'primary',
                'requires_clean_worktree' => true,
            ],
            'import' => [
                'label' => 'Import Bundle',
                'command' => 'php artisan self-hosted:import storage/framework/self-hosted-stage-ui storage/framework/self-hosted-import-ui --force',
                'note' => 'Import bundle dari stage internal ke target internal.',
                'artifact_path' => 'storage/framework/self-hosted-import-ui',
                'tone' => 'warning',
                'requires_clean_worktree' => true,
            ],
            'seed_workspace' => [
                'label' => 'Seed Workspace',
                'command' => 'php artisan self-hosted:seed-workspace storage/framework/self-hosted-workspace-ui --force',
                'note' => 'Bangun workspace seed lengkap untuk audit dan review.',
                'artifact_path' => 'storage/framework/self-hosted-workspace-ui',
                'tone' => 'success',
                'requires_clean_worktree' => true,
            ],
            'audit_workspace' => [
                'label' => 'Audit Workspace',
                'command' => 'php artisan self-hosted:audit-workspace storage/framework/self-hosted-workspace-ui --json',
                'note' => 'Audit dependency internal yang masih hilang di workspace.',
                'artifact_path' => 'storage/framework/self-hosted-workspace-ui',
                'tone' => 'dark',
                'requires_clean_worktree' => true,
            ],
            'materialize_repo' => [
                'label' => 'Materialize Repo',
                'command' => 'php artisan self-hosted:materialize-repo storage/framework/self-hosted-repo-ui --force',
                'note' => 'Bentuk candidate repo self-hosted siap ditinjau.',
                'artifact_path' => 'storage/framework/self-hosted-repo-ui',
                'tone' => 'danger',
                'requires_clean_worktree' => true,
            ],
            'publish_update_notice' => [
                'label' => 'Publish Update Notice',
                'command' => 'php artisan self-hosted:publish-update-notice storage/framework/self-hosted-update-notice-ui/_self_hosted_update_notice.json --json',
                'note' => 'Buat file metadata update manual yang siap dikirim ke instance self-hosted.',
                'artifact_path' => 'storage/framework/self-hosted-update-notice-ui/_self_hosted_update_notice.json',
                'tone' => 'info',
                'requires_clean_worktree' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function actionKeys(): array
    {
        return array_keys(self::definitions());
    }

    public function historyPath(): string
    {
        return storage_path('framework/self-hosted-toolkit/last-runs.json');
    }

    public function artifactDownloadPath(string $action): ?string
    {
        $definition = self::definitions()[$action] ?? null;

        if ($definition === null || ! is_string($definition['artifact_path']) || $definition['artifact_path'] === '') {
            return null;
        }

        return base_path($definition['artifact_path']);
    }

    public function artifactExists(string $action): bool
    {
        $artifactPath = $this->artifactDownloadPath($action);

        if ($artifactPath === null || ! File::exists($artifactPath)) {
            return false;
        }

        if (File::isDirectory($artifactPath)) {
            return count(File::allFiles($artifactPath)) > 0;
        }

        return true;
    }

    /**
     * @return array{is_dirty: bool, entries: list<string>, count: int}
     */
    public function worktreeStatus(): array
    {
        $entries = $this->dirtyWorktreeEntries();

        return [
            'is_dirty' => $entries !== [],
            'entries' => $entries,
            'count' => count($entries),
        ];
    }

    /**
     * @return list<string>
     */
    public function dirtyWorktreeEntries(): array
    {
        if ((bool) config('app.self_hosted_toolkit_ignore_dirty_worktree', false)) {
            return [];
        }

        try {
            $process = Process::fromShellCommandline('git status --short --untracked-files=normal', base_path());
            $process->setTimeout(15);
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            return collect(preg_split('/\r\n|\r|\n/', trim($process->getOutput())) ?: [])
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
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actionsWithHistory(): array
    {
        $history = $this->readHistory();
        $actions = [];

        foreach (self::definitions() as $actionKey => $definition) {
            $actions[] = [
                'key' => $actionKey,
                ...$definition,
                'history' => $history[$actionKey] ?? null,
                'artifact_exists' => $this->artifactExists($actionKey),
                'blocked_by_dirty_worktree' => $this->isBlockedByDirtyWorktree($actionKey),
            ];
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $action): array
    {
        $definition = self::definitions()[$action] ?? null;

        if ($definition === null) {
            throw new RuntimeException('Aksi toolkit self-hosted tidak dikenal.');
        }

        if ($this->isBlockedByDirtyWorktree($action)) {
            throw new RuntimeException('Worktree repo utama masih kotor. Commit/stash perubahan SaaS dan self-hosted dulu sebelum menjalankan aksi rilis ini.');
        }

        $startedAt = microtime(true);

        try {
            $process = Process::fromShellCommandline($definition['command'], base_path());
            $process->setTimeout(120);
            $process->run();

            $result = [
                'action' => $action,
                'label' => $definition['label'],
                'command' => $definition['command'],
                'success' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode() ?? -1,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'output' => $this->truncateOutput($process->getOutput().$process->getErrorOutput()),
                'artifact_path' => $definition['artifact_path'],
                'ran_at' => now()->toIso8601String(),
            ];
        } catch (ProcessTimedOutException $exception) {
            $result = [
                'action' => $action,
                'label' => $definition['label'],
                'command' => $definition['command'],
                'success' => false,
                'exit_code' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'output' => $this->truncateOutput($exception->getMessage()),
                'artifact_path' => $definition['artifact_path'],
                'ran_at' => now()->toIso8601String(),
            ];
        }

        $this->storeHistory($action, $result);

        return $result;
    }

    public function isBlockedByDirtyWorktree(string $action): bool
    {
        $definition = self::definitions()[$action] ?? null;

        if ($definition === null) {
            return false;
        }

        if (($definition['requires_clean_worktree'] ?? false) !== true) {
            return false;
        }

        return $this->dirtyWorktreeEntries() !== [];
    }

    /**
     * @return array{path: string, download_name: string, delete_after_send: bool, source_path: string, headers?: array<string, string>}
     */
    public function downloadArtifact(string $action): array
    {
        $definition = self::definitions()[$action] ?? null;

        if ($definition === null) {
            throw new RuntimeException('Aksi toolkit self-hosted tidak dikenal.');
        }

        $artifactAbsolutePath = $this->artifactDownloadPath($action);

        if ($artifactAbsolutePath === null || ! File::exists($artifactAbsolutePath)) {
            throw new RuntimeException('Artifact untuk aksi ini belum tersedia.');
        }

        if (File::isFile($artifactAbsolutePath)) {
            return [
                'path' => $artifactAbsolutePath,
                'download_name' => basename($artifactAbsolutePath),
                'delete_after_send' => false,
                'source_path' => $artifactAbsolutePath,
            ];
        }

        $archivePath = storage_path('framework/self-hosted-toolkit/downloads/'.$action.'.zip');
        File::ensureDirectoryExists(dirname($archivePath));

        if (File::exists($archivePath)) {
            File::delete($archivePath);
        }

        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip artifact toolkit.');
        }

        $rootLength = strlen(rtrim($artifactAbsolutePath, DIRECTORY_SEPARATOR)) + 1;

        foreach (File::allFiles($artifactAbsolutePath) as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            $zip->addFile($realPath, substr($realPath, $rootLength));
        }

        $zip->close();

        return [
            'path' => $archivePath,
            'download_name' => $action.'.zip',
            'delete_after_send' => true,
            'source_path' => $artifactAbsolutePath,
            'headers' => [
                'Content-Type' => 'application/zip',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readHistory(): array
    {
        if (! File::exists($this->historyPath())) {
            return [];
        }

        $decoded = json_decode((string) File::get($this->historyPath()), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function storeHistory(string $action, array $result): void
    {
        try {
            $history = $this->readHistory();
            $history[$action] = $result;

            File::ensureDirectoryExists(dirname($this->historyPath()));
            File::put($this->historyPath(), json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $exception) {
            Log::warning('Unable to persist self-hosted toolkit history.', [
                'action' => $action,
                'history_path' => $this->historyPath(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function truncateOutput(string $output): string
    {
        $normalized = trim($output);

        if ($normalized === '') {
            return '[tidak ada output]';
        }

        if (mb_strlen($normalized) <= self::OUTPUT_LIMIT_CHARS) {
            return $normalized;
        }

        return mb_substr($normalized, 0, self::OUTPUT_LIMIT_CHARS)."\n\n...[output dipotong]";
    }

    public function formatRunTime(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->format('d M Y H:i:s');
    }
}
