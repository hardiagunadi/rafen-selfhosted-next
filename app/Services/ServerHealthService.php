<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ServerHealthService
{
    public function __construct(
        private readonly FeatureGateService $featureGateService,
        private readonly WaMultiSessionManager $waMultiSessionManager,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return collect($this->definitions())
            ->filter(fn (array $definition): bool => $this->shouldInclude($definition))
            ->map(fn (array $definition): array => $this->snapshot($definition))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function control(string $serviceKey, ?string $requestedAction = null): array
    {
        $definition = $this->definitions()[$serviceKey] ?? null;

        if ($definition === null || ! $this->shouldInclude($definition)) {
            return [
                'success' => false,
                'message' => 'Layanan tidak dikenal.',
            ];
        }

        $service = $this->snapshot($definition);
        $action = in_array($requestedAction, ['restart', 'start_permanent'], true)
            ? $requestedAction
            : (string) $service['primary_action'];

        return match ($definition['driver']) {
            'pm2' => $this->controlPm2($definition, $action),
            default => $this->controlSystemd($definition, $action),
        };
    }

    /**
     * @return array{
     *     attempted:int,
     *     started:list<string>,
     *     already_running:list<string>,
     *     failed:list<array{name:string,message:string}>
     * }
     */
    public function startInactiveLicensedServices(): array
    {
        $summary = [
            'attempted' => 0,
            'started' => [],
            'already_running' => [],
            'failed' => [],
        ];

        foreach ($this->services() as $service) {
            $name = (string) ($service['name'] ?? $service['key'] ?? 'service');

            if ((bool) ($service['running'] ?? false)) {
                $summary['already_running'][] = $name;

                continue;
            }

            $summary['attempted']++;

            $result = $this->control((string) $service['key'], 'start_permanent');

            if ((bool) ($result['success'] ?? false)) {
                $summary['started'][] = $name;

                continue;
            }

            $summary['failed'][] = [
                'name' => $name,
                'message' => (string) ($result['message'] ?? "Gagal menjalankan {$name}."),
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'rafen-queue' => [
                'key' => 'rafen-queue',
                'name' => 'Queue Worker',
                'driver' => 'systemd',
                'unit' => 'rafen-queue',
            ],
            'rafen-schedule.timer' => [
                'key' => 'rafen-schedule.timer',
                'name' => 'Scheduler Timer',
                'driver' => 'systemd',
                'unit' => 'rafen-schedule.timer',
            ],
            'freeradius' => [
                'key' => 'freeradius',
                'name' => 'FreeRADIUS',
                'driver' => 'systemd',
                'unit' => 'freeradius',
                'feature' => 'radius',
            ],
            'genieacs-cwmp' => [
                'key' => 'genieacs-cwmp',
                'name' => 'GenieACS CWMP',
                'driver' => 'systemd',
                'unit' => 'genieacs-cwmp',
                'feature' => 'genieacs',
            ],
            'genieacs-nbi' => [
                'key' => 'genieacs-nbi',
                'name' => 'GenieACS NBI',
                'driver' => 'systemd',
                'unit' => 'genieacs-nbi',
                'feature' => 'genieacs',
            ],
            'wa-multi-session' => [
                'key' => 'wa-multi-session',
                'name' => 'PM2 / wa-multi-session',
                'driver' => 'pm2',
                'feature' => 'wa',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function snapshot(array $definition): array
    {
        return match ($definition['driver']) {
            'pm2' => $this->pm2Snapshot($definition),
            default => $this->systemdSnapshot($definition),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function systemdSnapshot(array $definition): array
    {
        $unit = (string) $definition['unit'];
        $status = $this->systemdStatus($unit);
        $running = $status === 'active';
        $primaryAction = $running ? 'restart' : 'start_permanent';

        return array_merge($definition, [
            'status' => $status,
            'running' => $running,
            'status_label' => $running ? 'Running' : strtoupper($status !== '' ? $status : 'unknown'),
            'primary_action' => $primaryAction,
            'primary_action_label' => $primaryAction === 'restart' ? 'Restart' : 'Start Permanen',
            'primary_action_icon' => $primaryAction === 'restart' ? 'fa-redo-alt' : 'fa-play',
            'primary_action_class' => $primaryAction === 'restart' ? 'btn-outline-warning' : 'btn-outline-success',
            'meta_text' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function pm2Snapshot(array $definition): array
    {
        $status = $this->waMultiSessionManager->status();
        $running = (bool) ($status['running'] ?? false);
        $primaryAction = $running ? 'restart' : 'start_permanent';
        $pm2Status = trim((string) ($status['pm2_status'] ?? 'stopped'));
        $metaSegments = array_values(array_filter([
            $pm2Status !== '' ? 'PM2: '.$pm2Status : null,
            ! empty($status['pm2_pid']) ? 'PID: '.$status['pm2_pid'] : null,
            ! empty($status['url']) ? 'URL: '.$status['url'] : null,
        ]));

        return array_merge($definition, [
            'unit' => (string) ($status['name'] ?? 'wa-multi-session'),
            'status' => $running ? 'active' : ($pm2Status !== '' ? $pm2Status : 'stopped'),
            'running' => $running,
            'status_label' => $running ? 'Running' : strtoupper($pm2Status !== '' ? $pm2Status : 'stopped'),
            'primary_action' => $primaryAction,
            'primary_action_label' => $primaryAction === 'restart' ? 'Restart' : 'Start Permanen',
            'primary_action_icon' => $primaryAction === 'restart' ? 'fa-redo-alt' : 'fa-play',
            'primary_action_class' => $primaryAction === 'restart' ? 'btn-outline-warning' : 'btn-outline-success',
            'meta_text' => $metaSegments !== [] ? implode(' | ', $metaSegments) : null,
            'meta' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function controlSystemd(array $definition, string $action): array
    {
        $unit = (string) $definition['unit'];
        $name = (string) $definition['name'];
        $command = $this->systemdControlCommand($unit, $action);
        $process = Process::timeout(30)->run($command);
        $rawError = trim($process->errorOutput() ?: $process->output());

        if (! $process->successful() && $this->shouldRetryAfterDaemonReload($unit, $rawError)) {
            $reload = Process::timeout(30)->run('sudo /bin/systemctl daemon-reload');
            $reloadError = trim($reload->errorOutput() ?: $reload->output());

            if ($reload->successful()) {
                $process = Process::timeout(30)->run($command);
                $rawError = trim($process->errorOutput() ?: $process->output());
            } elseif ($reloadError !== '') {
                $rawError .= ($rawError !== '' ? ' ' : '').'Daemon reload gagal: '.$reloadError;
            }
        }

        usleep(500000);

        $service = $this->snapshot($definition);
        $ok = (bool) ($service['running'] ?? false);

        return [
            'success' => $ok,
            'action' => $action,
            'status' => $service['status'],
            'service' => $service,
            'message' => $ok
                ? ($action === 'start_permanent'
                    ? "{$name} berhasil dijalankan permanen."
                    : "{$name} berhasil di-restart.")
                : ($rawError !== ''
                    ? $this->formatSystemdFailureMessage($name, $rawError)
                    : (($action === 'start_permanent'
                        ? "Gagal menjalankan permanen {$name}."
                        : "Restart {$name} gagal.").' Status: '.$service['status'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function controlPm2(array $definition, string $action): array
    {
        $result = $action === 'start_permanent'
            ? $this->waMultiSessionManager->startPermanent()
            : $this->waMultiSessionManager->restart();

        $service = $this->snapshot($definition);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'action' => $action,
            'status' => $service['status'],
            'service' => $service,
            'message' => (string) ($result['message'] ?? 'Aksi service PM2 selesai.'),
        ];
    }

    private function systemdStatus(string $unit): string
    {
        $result = Process::timeout(10)->run('/bin/systemctl is-active '.$unit);
        $status = trim($result->output());

        if ($status !== '') {
            return $status;
        }

        return trim($result->errorOutput());
    }

    private function systemdControlCommand(string $unit, string $action): string
    {
        return $action === 'start_permanent'
            ? 'sudo /bin/systemctl enable --now '.$unit
            : 'sudo /bin/systemctl restart '.$unit;
    }

    private function shouldRetryAfterDaemonReload(string $unit, string $rawError): bool
    {
        if ($rawError === '') {
            return false;
        }

        if (! str_contains($rawError, 'does not exist')) {
            return false;
        }

        return File::exists('/etc/systemd/system/'.$unit.'.service')
            || File::exists('/etc/systemd/system/'.$unit)
            || File::exists('/lib/systemd/system/'.$unit.'.service')
            || File::exists('/lib/systemd/system/'.$unit);
    }

    private function formatSystemdFailureMessage(string $name, string $rawError): string
    {
        $message = "Aksi {$name} gagal: {$rawError}";

        if (str_contains($rawError, 'sudo: a terminal is required')
            || str_contains($rawError, 'sudo: a password is required')) {
            return $message.' Jalankan ulang installer/deploy self-hosted sebagai root agar sudoers Server Health terpasang.';
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function shouldInclude(array $definition): bool
    {
        $feature = trim((string) ($definition['feature'] ?? ''));

        if ($feature === '') {
            return true;
        }

        if (! (bool) config('license.self_hosted_enabled', false)) {
            return true;
        }

        return $this->featureGateService->isEnabled($feature);
    }
}
