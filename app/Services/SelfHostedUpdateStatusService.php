<?php

namespace App\Services;

use App\Models\SelfHostedUpdateState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class SelfHostedUpdateStatusService
{
    private ?bool $storageReady = null;

    public function __construct(
        private readonly SelfHostedUpdateManifestService $manifestService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $state = $this->state();
        $currentVersion = $this->currentVersion();
        $currentCommit = $this->currentCommit();
        $currentRef = $this->currentRef();
        $manifestUrl = $this->manifestService->manifestUrl();

        return [
            'channel' => $this->channel(),
            'manifest_url' => $manifestUrl,
            'is_configured' => $manifestUrl !== '',
            'is_storage_ready' => $this->isStorageReady(),
            'current_version' => $state?->current_version ?: $currentVersion,
            'current_commit' => $state?->current_commit ?: $currentCommit,
            'current_ref' => $state?->current_ref ?: $currentRef,
            'latest_version' => $state?->latest_version,
            'latest_commit' => $state?->latest_commit,
            'latest_ref' => $state?->latest_ref,
            'latest_published_at' => $state?->latest_published_at,
            'latest_manifest_url' => $state?->latest_manifest_url,
            'latest_release_notes_url' => $state?->latest_release_notes_url,
            'update_available' => (bool) ($state?->update_available ?? false),
            'last_checked_at' => $state?->last_checked_at,
            'last_check_status' => $state?->last_check_status ?? ($this->isStorageReady() ? 'never' : 'storage_unavailable'),
            'last_check_message' => $state?->last_check_message ?: $this->defaultMessage($manifestUrl),
            'last_applied_at' => $state?->last_applied_at,
            'last_apply_status' => $state?->last_apply_status,
            'last_apply_message' => $state?->last_apply_message,
            'last_heartbeat_at' => $state?->last_heartbeat_at,
            'last_successful_heartbeat_at' => $state?->last_successful_heartbeat_at,
            'last_heartbeat_status' => $state?->last_heartbeat_status ?? $this->defaultHeartbeatStatus(),
            'last_heartbeat_message' => $state?->last_heartbeat_message ?: $this->defaultHeartbeatMessage(),
            'last_heartbeat_status_id' => $state?->last_heartbeat_status_id,
            'rollback_ref' => $state?->rollback_ref,
            'manifest_payload' => $state?->manifest_payload,
            'last_heartbeat_response' => $state?->last_heartbeat_response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        if (! $this->isStorageReady()) {
            throw new RuntimeException('Tabel self_hosted_update_states belum tersedia. Jalankan php artisan migrate --force terlebih dahulu.');
        }

        $state = SelfHostedUpdateState::query()->firstOrNew([
            'channel' => $this->channel(),
        ]);

        $state->fill([
            'current_version' => $this->currentVersion(),
            'current_commit' => $this->currentCommit(),
            'current_ref' => $this->currentRef(),
        ]);

        $manifestUrl = $this->manifestService->manifestUrl();

        if ($manifestUrl === '') {
            $state->fill([
                'latest_manifest_url' => null,
                'update_available' => false,
                'last_checked_at' => now(),
                'last_check_status' => 'not_configured',
                'last_check_message' => 'SELF_HOSTED_UPDATE_MANIFEST_URL belum diisi.',
            ])->save();

            return $this->snapshot();
        }

        try {
            $manifest = $this->manifestService->fetch();
        } catch (RuntimeException $exception) {
            $state->fill([
                'latest_manifest_url' => $manifestUrl,
                'update_available' => false,
                'last_checked_at' => now(),
                'last_check_status' => 'error',
                'last_check_message' => $exception->getMessage(),
            ])->save();

            return $this->snapshot();
        }

        if ($manifest['channel'] !== $this->channel()) {
            $state->fill([
                'latest_manifest_url' => $manifest['manifest_url'],
                'update_available' => false,
                'last_checked_at' => now(),
                'last_check_status' => 'error',
                'last_check_message' => 'Channel manifest tidak cocok dengan channel instance ini.',
                'manifest_payload' => $manifest['raw'],
            ])->save();

            return $this->snapshot();
        }

        $updateAvailable = $this->hasUpdate(
            currentVersion: (string) $state->current_version,
            currentCommit: $state->current_commit,
            latestVersion: (string) $manifest['version'],
            latestCommit: (string) $manifest['commit'],
        );

        $state->fill([
            'latest_version' => $manifest['version'],
            'latest_commit' => $manifest['commit'],
            'latest_ref' => (string) data_get($manifest, 'package.ref', $manifest['tag']),
            'latest_published_at' => $manifest['published_at'],
            'latest_manifest_url' => $manifest['manifest_url'],
            'latest_release_notes_url' => $manifest['release_notes_url'],
            'update_available' => $updateAvailable,
            'last_checked_at' => now(),
            'last_check_status' => 'ok',
            'last_check_message' => $updateAvailable
                ? 'Update tersedia untuk instance ini.'
                : 'Instance sudah menggunakan release terbaru.',
            'manifest_payload' => $manifest['raw'],
        ])->save();

        return $this->snapshot();
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    public function recordHeartbeat(
        string $status,
        ?string $message = null,
        ?int $statusId = null,
        ?array $response = null,
        ?Carbon $sentAt = null,
    ): void {
        if (! $this->isStorageReady()) {
            return;
        }

        $state = SelfHostedUpdateState::query()->firstOrNew([
            'channel' => $this->channel(),
        ]);

        $state->fill([
            'current_version' => $state->current_version ?: $this->currentVersion(),
            'current_commit' => $state->current_commit ?: $this->currentCommit(),
            'current_ref' => $state->current_ref ?: $this->currentRef(),
            'last_heartbeat_at' => $sentAt ?? now(),
            'last_successful_heartbeat_at' => $status === 'success'
                ? ($sentAt ?? now())
                : $state->last_successful_heartbeat_at,
            'last_heartbeat_status' => $status,
            'last_heartbeat_message' => $message,
            'last_heartbeat_status_id' => $statusId,
            'last_heartbeat_response' => $response,
        ])->save();
    }

    private function channel(): string
    {
        return $this->manifestService->channel();
    }

    private function currentVersion(): string
    {
        $version = trim((string) config('app.version', 'main-dev'));

        return $version !== '' ? $version : 'main-dev';
    }

    private function currentCommit(): ?string
    {
        $configuredCommit = trim((string) config('app.commit', ''));

        if ($configuredCommit !== '') {
            return $configuredCommit;
        }

        try {
            $process = Process::fromShellCommandline('git rev-parse --short HEAD', $this->manifestService->workdir());
            $process->setTimeout(5);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $commit = trim($process->getOutput());

            return $commit !== '' ? $commit : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function currentRef(): ?string
    {
        try {
            $process = Process::fromShellCommandline(
                '(git describe --tags --exact-match HEAD || git rev-parse --short HEAD)',
                $this->manifestService->workdir()
            );
            $process->setTimeout(5);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $ref = trim($process->getOutput());

            return $ref !== '' ? $ref : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isStorageReady(): bool
    {
        if ($this->storageReady !== null) {
            return $this->storageReady;
        }

        try {
            return $this->storageReady = Schema::hasTable('self_hosted_update_states');
        } catch (Throwable) {
            return $this->storageReady = false;
        }
    }

    private function state(): ?SelfHostedUpdateState
    {
        if (! $this->isStorageReady()) {
            return null;
        }

        return SelfHostedUpdateState::query()
            ->where('channel', $this->channel())
            ->first();
    }

    private function defaultMessage(string $manifestUrl): ?string
    {
        if (! $this->isStorageReady()) {
            return 'Tabel status update belum tersedia. Jalankan migrate agar status update bisa disimpan.';
        }

        if ($manifestUrl === '') {
            return 'SELF_HOSTED_UPDATE_MANIFEST_URL belum diisi.';
        }

        return 'Belum pernah melakukan cek update.';
    }

    private function defaultHeartbeatStatus(): string
    {
        return $this->hasHeartbeatConfiguration() ? 'never' : 'not_configured';
    }

    private function defaultHeartbeatMessage(): string
    {
        if ($this->hasHeartbeatConfiguration()) {
            return 'Belum pernah mengirim heartbeat status ke SaaS.';
        }

        return 'SELF_HOSTED_REGISTRY_URL atau SELF_HOSTED_REGISTRY_TOKEN belum diisi.';
    }

    private function hasHeartbeatConfiguration(): bool
    {
        $registryUrl = trim((string) config('services.self_hosted_registry.url', ''));
        $token = trim((string) config('services.self_hosted_registry.token', ''));

        return $registryUrl !== '' && $token !== '';
    }

    private function hasUpdate(string $currentVersion, ?string $currentCommit, string $latestVersion, string $latestCommit): bool
    {
        if ($currentVersion !== $latestVersion) {
            return true;
        }

        if ($currentCommit !== null && $currentCommit !== '' && $currentCommit !== $latestCommit) {
            return true;
        }

        return false;
    }
}
