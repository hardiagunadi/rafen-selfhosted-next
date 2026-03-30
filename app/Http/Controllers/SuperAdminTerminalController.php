<?php

namespace App\Http\Controllers;

use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class SuperAdminTerminalController extends Controller
{
    use LogsActivity;

    private const int COMMAND_TIMEOUT_SECONDS = 25;

    private const int OUTPUT_LIMIT_CHARS = 15000;

    /**
     * @var list<array{label: string, command: string, note: string}>
     */
    private const array COMMAND_PRESETS = [
        [
            'label' => 'Sync RADIUS Replies',
            'command' => 'php artisan radius:sync-replies',
            'note' => 'Sinkronisasi PPP/Hotspot/Voucher ke radcheck & radreply.',
        ],
        [
            'label' => 'Generate Invoice',
            'command' => 'php artisan invoice:generate-upcoming --days=7 --dry-run',
            'note' => 'Simulasi invoice upcoming tanpa menulis data.',
        ],
        [
            'label' => 'Sync Sessions',
            'command' => 'php artisan sessions:sync',
            'note' => 'Sinkronisasi sesi PPPoE/Hotspot dari MikroTik.',
        ],
        [
            'label' => 'Clear Cache',
            'command' => 'php artisan cache:clear',
            'note' => 'Bersihkan cache aplikasi/scheduler lock.',
        ],
        [
            'label' => 'Status FreeRADIUS',
            'command' => 'systemctl status freeradius',
            'note' => 'Cek status layanan FreeRADIUS.',
        ],
        [
            'label' => 'Restart FreeRADIUS',
            'command' => 'systemctl restart freeradius',
            'note' => 'Restart layanan FreeRADIUS.',
        ],
        [
            'label' => 'Tail Radius Log',
            'command' => 'tail -f /var/log/freeradius/radius.log',
            'note' => 'Pantau log autentikasi RADIUS real-time.',
        ],
        [
            'label' => 'WG Status',
            'command' => 'wg show',
            'note' => 'Lihat status interface dan peer WireGuard.',
        ],
        [
            'label' => 'WG Restart',
            'command' => 'systemctl restart wg-quick@wg0',
            'note' => 'Restart service WireGuard interface wg0.',
        ],
        [
            'label' => 'Scheduler Sessions Log',
            'command' => 'sudo journalctl -u rafen-schedule.service --since "10 minutes ago" | grep sessions',
            'note' => 'Audit apakah sessions:sync dieksekusi scheduler.',
        ],
        [
            'label' => 'Tail Laravel Log',
            'command' => 'tail -50 /var/www/rafen/storage/logs/laravel.log',
            'note' => 'Cek error terbaru aplikasi Laravel.',
        ],
        [
            'label' => 'Self-Hosted Manifest',
            'command' => 'php artisan self-hosted:manifest --json',
            'note' => 'Lihat daftar file dan touchpoint cluster self-hosted.',
        ],
        [
            'label' => 'Self-Hosted Cutover Plan',
            'command' => 'php artisan self-hosted:cutover-plan --json',
            'note' => 'Lihat runbook cutover repo self-hosted dari UI super admin.',
        ],
        [
            'label' => 'Self-Hosted Stage Bundle',
            'command' => 'php artisan self-hosted:stage storage/framework/self-hosted-stage-ui --force',
            'note' => 'Buat bundle staging self-hosted ke direktori kerja internal.',
        ],
        [
            'label' => 'Self-Hosted Import Bundle',
            'command' => 'php artisan self-hosted:import storage/framework/self-hosted-stage-ui storage/framework/self-hosted-import-ui --force',
            'note' => 'Import bundle self-hosted ke workspace target internal.',
        ],
        [
            'label' => 'Self-Hosted Seed Workspace',
            'command' => 'php artisan self-hosted:seed-workspace storage/framework/self-hosted-workspace-ui --force',
            'note' => 'Buat workspace seed lengkap untuk review dan audit.',
        ],
        [
            'label' => 'Self-Hosted Audit Workspace',
            'command' => 'php artisan self-hosted:audit-workspace storage/framework/self-hosted-workspace-ui --json',
            'note' => 'Audit dependency internal App yang masih tertinggal di workspace seed.',
        ],
        [
            'label' => 'Self-Hosted Materialize Repo',
            'command' => 'php artisan self-hosted:materialize-repo storage/framework/self-hosted-repo-ui --force',
            'note' => 'Bentuk candidate repo self-hosted siap review dari UI.',
        ],
    ];

    /**
     * @var list<string>
     */
    private const array COMMAND_PATTERNS = [
        '/^(?:sudo(?:\\s+-n)?\\s+)?php(?:\\s+artisan|\\s+\/var\/www\/rafen\/artisan)\\s+[a-zA-Z0-9:_-]+(?:\\s+(?:(?:--?[a-zA-Z0-9][a-zA-Z0-9:_-]*(?:=(?:"[^"]*"|\'[^\']*\'|[^\\s]+))?)|(?:"[^"]*"|\'[^\']*\'|[\\/a-zA-Z0-9._:@-]+)))*$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?systemctl\\s+(?:status|restart|reload|stop|start)\\s+[a-zA-Z0-9@._-]+$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?journalctl\\s+-u\\s+[a-zA-Z0-9@._-]+(?:\\s+--since\\s+(?:"[^"]+"|\'[^\']+\'|[^\\s]+))?(?:\\s+\\|\\s+grep\\s+[a-zA-Z0-9._:-]+)?$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?tail\\s+-f\\s+\/var\/log\/freeradius\/radius\\.log$/',
        '/^tail\\s+-(?:\\d+|n\\s+\\d+)\\s+\/var\/www\/rafen\/storage\/logs\/laravel\\.log$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?wg\\s+show(?:\\s+[a-zA-Z0-9@._-]+)?$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?wg\\s+showconf\\s+[a-zA-Z0-9@._-]+$/',
        '/^ping\\s+(?:-c\\s+\\d+\\s+)?(?:\\d{1,3}(?:\\.\\d{1,3}){3}|[a-zA-Z0-9.-]+)$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?ls\\s+-la\\s+\/etc\/freeradius\/3\\.0\/mods-enabled\/sql$/',
        '/^(?:sudo(?:\\s+-n)?\\s+)?ln\\s+-s\\s+\/etc\/freeradius\/3\\.0\/mods-available\/sql\\s+\/etc\/freeradius\/3\\.0\/mods-enabled\/sql$/',
        '/^crontab\\s+-l$/',
    ];

    public function index(): View
    {
        return view('super-admin.terminal.index', [
            'presets' => self::COMMAND_PRESETS,
            'timeoutSeconds' => self::COMMAND_TIMEOUT_SECONDS,
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:500'],
        ]);

        $rawCommand = $this->normalizeCommand($validated['command']);

        if ($rawCommand === '') {
            return response()->json([
                'success' => false,
                'message' => 'Perintah tidak boleh kosong.',
            ], 422);
        }

        if (! $this->isAllowedCommand($rawCommand)) {
            return response()->json([
                'success' => false,
                'message' => 'Perintah tidak diizinkan. Gunakan perintah yang tercantum di Pusat Bantuan RAFEN.',
            ], 422);
        }

        $command = $this->prepareExecutionCommand($rawCommand);

        $startedAt = microtime(true);

        try {
            $process = Process::fromShellCommandline($command, base_path());
            $process->setTimeout(self::COMMAND_TIMEOUT_SECONDS);
            $process->run();

            $output = $this->sanitizeOutput($process->getOutput().$process->getErrorOutput());
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $success = $process->isSuccessful();
            $exitCode = $process->getExitCode() ?? -1;

            $this->logActivity(
                'super_admin_terminal_run',
                'SystemCommand',
                null,
                Str::limit($command, 255, '...'),
                (int) $request->user()->id,
                [
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'success' => $success,
                    'duration_ms' => $durationMs,
                ]
            );

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Perintah berhasil dijalankan.' : 'Perintah selesai dengan error.',
                'command' => $command,
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
                'output' => $output,
            ]);
        } catch (ProcessTimedOutException $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->logActivity(
                'super_admin_terminal_run_timeout',
                'SystemCommand',
                null,
                Str::limit($command, 255, '...'),
                (int) $request->user()->id,
                [
                    'command' => $command,
                    'duration_ms' => $durationMs,
                    'timeout_seconds' => self::COMMAND_TIMEOUT_SECONDS,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Perintah dihentikan karena melewati batas waktu eksekusi.',
                'command' => $command,
                'exit_code' => null,
                'duration_ms' => $durationMs,
                'output' => $this->sanitizeOutput($exception->getMessage()),
            ], 422);
        }
    }

    private function normalizeCommand(string $command): string
    {
        return trim((string) preg_replace('/[\t\n\r]+/', ' ', $command));
    }

    private function enforceNonInteractiveSudo(string $command): string
    {
        if (! Str::startsWith($command, 'sudo ')) {
            return $command;
        }

        if (preg_match('/^sudo\\s+-n\\b/', $command) === 1) {
            return $command;
        }

        return 'sudo -n '.substr($command, 5);
    }

    private function prepareExecutionCommand(string $command): string
    {
        if ($this->shouldAutoElevate($command)) {
            return $this->enforceNonInteractiveSudo('sudo '.$command);
        }

        return $this->enforceNonInteractiveSudo($command);
    }

    private function shouldAutoElevate(string $command): bool
    {
        if (Str::startsWith($command, 'sudo ')) {
            return false;
        }

        return Str::startsWith($command, [
            'systemctl ',
            'journalctl ',
            'wg ',
            'tail -f /var/log/freeradius/radius.log',
            'ls -la /etc/freeradius/3.0/mods-enabled/sql',
            'ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql',
        ]);
    }

    private function isAllowedCommand(string $command): bool
    {
        foreach (self::COMMAND_PATTERNS as $pattern) {
            if (preg_match($pattern, $command) === 1) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeOutput(string $output): string
    {
        $normalized = trim($output);

        if ($normalized === '') {
            return '[tidak ada output]';
        }

        if (mb_strlen($normalized) <= self::OUTPUT_LIMIT_CHARS) {
            return $normalized;
        }

        return mb_substr($normalized, 0, self::OUTPUT_LIMIT_CHARS).'\n\n...[output dipotong]';
    }
}
