<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class WaMultiSessionManager
{
    public function ensureRunning(): array
    {
        $status = $this->status();

        if (($status['running'] ?? false) === true) {
            return [
                'success' => true,
                'message' => 'wa-multi-session sudah berjalan.',
                'data' => $status,
            ];
        }

        $start = Process::timeout(30)->run($this->buildShellCommand($this->startCommand()));

        if (! $start->successful()) {
            return [
                'success' => false,
                'message' => 'Gagal menjalankan wa-multi-session via PM2: '.trim($start->errorOutput() ?: $start->output()),
                'data' => $this->status(),
            ];
        }

        usleep(600000);

        return [
            'success' => true,
            'message' => 'wa-multi-session berhasil dijalankan via PM2.',
            'data' => $this->status(),
        ];
    }

    public function status(): array
    {
        $context = $this->detectProcessContext();
        $process = $context['process'];
        $pm2Home = $context['pm2_home'];
        $pm2Online = $process !== null && ($process['pm2_env']['status'] ?? null) === 'online';

        // Jika PM2 tidak terbaca (beda user/daemon), fallback ke HTTP health check
        $running = $pm2Online || $this->isReachableViaHttp();

        return [
            'running' => $running,
            'name' => (string) config('wa.multi_session.pm2_name', 'wa-multi-session'),
            'host' => config('wa.multi_session.host'),
            'port' => (int) config('wa.multi_session.port', 3100),
            'url' => $this->baseUrl(),
            'pm2_bin' => (string) config('wa.multi_session.pm2_bin', 'pm2'),
            'pm2_pid' => $process['pid'] ?? null,
            'pm2_status' => $pm2Online ? 'online' : ($running ? 'online (http)' : 'stopped'),
            'pm2_home' => $pm2Home,
            'log_file' => $this->resolveLogFilePath(),
        ];
    }

    /**
     * Cek apakah service merespons via HTTP (fallback jika PM2 beda daemon/user).
     */
    private function isReachableViaHttp(): bool
    {
        try {
            $headers = [];
            $token = trim((string) config('wa.multi_session.auth_token', ''));
            $key = trim((string) config('wa.multi_session.master_key', ''));

            if ($token !== '') {
                $headers['Authorization'] = $token;
            }

            if ($key !== '') {
                $headers['key'] = $key;
            }

            $response = Http::timeout(3)
                ->withHeaders($headers)
                ->get($this->baseUrl().'/status');

            return $response->successful() || $response->status() === 404;
        } catch (\Throwable) {
            return false;
        }
    }

    public function restart(): array
    {
        $name = $this->pm2Name();
        $pm2 = $this->pm2Bin();
        $pm2Home = $this->detectProcessContext()['pm2_home'] ?? $this->primaryPm2Home();

        $restart = Process::timeout(20)->run($this->buildShellCommand("{$pm2} restart {$name} --update-env", $pm2Home));

        if (! $restart->successful()) {
            return $this->ensureRunning();
        }

        usleep(600000);

        return [
            'success' => true,
            'message' => 'wa-multi-session berhasil di-restart via PM2.',
            'data' => $this->status(),
        ];
    }

    private function findProcess(?string $pm2Home = null): ?array
    {
        $pm2 = $this->pm2Bin();
        $result = Process::timeout(15)->run($this->buildShellCommand("{$pm2} jlist", $pm2Home ?? $this->primaryPm2Home()));

        if (! $result->successful()) {
            return null;
        }

        $list = json_decode((string) $result->output(), true);

        if (! is_array($list)) {
            return null;
        }

        $targetName = $this->pm2Name();

        foreach ($list as $item) {
            if (is_array($item) && ($item['name'] ?? null) === $targetName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{process: ?array, pm2_home: ?string}
     */
    private function detectProcessContext(): array
    {
        foreach ($this->candidatePm2Homes() as $pm2Home) {
            $process = $this->findProcess($pm2Home);

            if ($process !== null) {
                return [
                    'process' => $process,
                    'pm2_home' => $pm2Home,
                ];
            }
        }

        return [
            'process' => null,
            'pm2_home' => $this->primaryPm2Home(),
        ];
    }

    private function startCommand(): string
    {
        $path = (string) config('wa.multi_session.path', base_path('wa-multi-session'));
        $script = (string) config('wa.multi_session.script', 'gateway-server.cjs');
        $logFile = $this->resolveLogFilePath();
        $dbConnection = (string) config('database.default', 'mysql');
        $dbConfig = (array) config('database.connections.'.$dbConnection, []);

        $env = [
            'WA_MS_HOST' => (string) config('wa.multi_session.host', '127.0.0.1'),
            'WA_MS_PORT' => (string) config('wa.multi_session.port', 3100),
            'WA_MS_AUTH_TOKEN' => (string) config('wa.multi_session.auth_token', ''),
            'WA_MS_MASTER_KEY' => (string) config('wa.multi_session.master_key', ''),
            'WA_MS_DB_HOST' => (string) ($dbConfig['host'] ?? '127.0.0.1'),
            'WA_MS_DB_PORT' => (string) ($dbConfig['port'] ?? 3306),
            'WA_MS_DB_NAME' => (string) ($dbConfig['database'] ?? ''),
            'WA_MS_DB_USER' => (string) ($dbConfig['username'] ?? ''),
            'WA_MS_DB_PASSWORD' => (string) ($dbConfig['password'] ?? ''),
            'WA_MS_DB_TABLE' => (string) config('wa.multi_session.db_table', 'wa_multi_session_auth_store'),
            'WA_MS_WEBHOOK_URL' => (string) config('wa.multi_session.webhook_url', ''),
        ];

        $exports = collect($env)
            ->map(fn (string $value, string $key): string => $key.'='.escapeshellarg($value))
            ->implode(' ');

        $pm2 = $this->pm2Bin();
        $name = $this->pm2Name();
        $logDir = dirname($logFile);

        return 'cd '.escapeshellarg($path)
            .' && mkdir -p '.escapeshellarg($logDir)
            .' && touch '.escapeshellarg($logFile)
            .' && chmod 0666 '.escapeshellarg($logFile)
            .' && env '.$exports.' '.$pm2
            .' start '.escapeshellarg($script)
            .' --name '.escapeshellarg($name)
            .' --time'
            .' --output '.escapeshellarg($logFile)
            .' --error '.escapeshellarg($logFile);
    }

    private function buildShellCommand(string $inner, string $pm2Home): string
    {
        return 'bash -lc '.escapeshellarg("PM2_HOME={$pm2Home} {$inner}");
    }

    private function baseUrl(): string
    {
        return 'http://'.config('wa.multi_session.host', '127.0.0.1').':'.(int) config('wa.multi_session.port', 3100);
    }

    private function pm2Name(): string
    {
        return (string) config('wa.multi_session.pm2_name', 'wa-multi-session');
    }

    private function pm2Bin(): string
    {
        $configured = trim((string) config('wa.multi_session.pm2_bin', 'pm2'));

        if ($configured === '' || $configured === 'npx pm2') {
            return 'pm2';
        }

        return $configured;
    }

    private function resolveLogFilePath(): string
    {
        $configured = trim((string) config('wa.multi_session.log_file', ''));
        $fallback = storage_path('logs/'.$this->pm2Name().'-pm2.log');

        if ($configured === '') {
            return $fallback;
        }

        if (is_file($configured)) {
            return is_writable($configured) ? $configured : $fallback;
        }

        $directory = dirname($configured);

        if (is_dir($directory) && is_writable($directory)) {
            return $configured;
        }

        return $fallback;
    }

    private function primaryPm2Home(): string
    {
        return trim((string) config('wa.multi_session.pm2_home', '/var/www/.pm2'));
    }

    /**
     * @return list<string>
     */
    private function candidatePm2Homes(): array
    {
        $homes = [
            $this->primaryPm2Home(),
            '/var/www/.pm2',
            $this->currentUserPm2Home(),
            ...$this->discoveredPm2Homes(),
        ];

        return array_values(array_unique(array_filter(array_map(
            static fn ($home): string => trim((string) $home),
            $homes
        ))));
    }

    private function currentUserPm2Home(): ?string
    {
        if (! function_exists('posix_geteuid') || ! function_exists('posix_getpwuid')) {
            return null;
        }

        $currentUser = posix_getpwuid(posix_geteuid());
        $homeDirectory = is_array($currentUser) ? trim((string) ($currentUser['dir'] ?? '')) : '';

        if ($homeDirectory === '') {
            return null;
        }

        return $homeDirectory.'/.pm2';
    }

    /**
     * @return list<string>
     */
    private function discoveredPm2Homes(): array
    {
        $candidates = array_merge(
            glob('/home/*/.pm2', GLOB_ONLYDIR) ?: [],
            glob('/root/.pm2', GLOB_ONLYDIR) ?: [],
        );

        return array_values(array_filter(array_map(
            static fn ($path): string => trim((string) $path),
            $candidates
        )));
    }
}
