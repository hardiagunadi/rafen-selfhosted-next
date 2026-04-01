<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RadiusClientsSynchronizer
{
    private const string SYNC_HELPER_PATH = '/usr/local/bin/rafen-sync-radius-clients';

    public function __construct(private Filesystem $filesystem) {}

    /**
     * @throws FileNotFoundException
     * @throws ProcessFailedException
     */
    public function sync(): void
    {
        $connections = MikrotikConnection::query()
            ->where('is_active', true)
            ->whereNotNull('radius_secret')
            ->with('wgPeer')
            ->get();

        $payload = $this->buildClientsPayload($connections);
        $path = (string) config('radius.clients_path');
        $directory = dirname($path);

        // Try direct write first
        $canWrite = $this->filesystem->isDirectory($directory)
            && $this->filesystem->isWritable($directory)
            && (! $this->filesystem->exists($path) || $this->filesystem->isWritable($path));

        if ($canWrite) {
            $this->filesystem->put($path, $payload);
            $this->reloadRadius();
            return;
        }

        // Fallback: use sudo wrapper script (requires /etc/sudoers.d/rafen-freeradius entry)
        $process = Process::fromShellCommandline('sudo -n '.self::SYNC_HELPER_PATH);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = $this->processOutput($process);
            $hint = $this->needsFreeRadiusBootstrap($errorOutput)
                ? ' Jalankan `bash install-selfhosted.sh deploy` sebagai root agar helper dan sudoers FreeRADIUS terpasang.'
                : '';

            throw new RuntimeException(
                "Tidak dapat menulis ke {$directory} dan fallback sudo juga gagal: {$errorOutput}.{$hint}"
            );
        }
    }

    /**
     * Write payload to file and reload FreeRADIUS (called directly when writable).
     */
    public function writeAndReload(string $payload): void
    {
        $path = (string) config('radius.clients_path');
        $this->filesystem->put($path, $payload);
        $this->reloadRadius();
    }

    public function buildPayload(): string
    {
        $connections = MikrotikConnection::query()
            ->where('is_active', true)
            ->whereNotNull('radius_secret')
            ->with('wgPeer')
            ->get();

        return $this->buildClientsPayload($connections);
    }

    private function reloadRadius(): void
    {
        $command = (string) config('radius.reload_command');
        if ($command === '') {
            return;
        }

        $process = $this->runShellCommand($command);

        if ($process->isSuccessful()) {
            return;
        }

        $errorOutput = $this->processOutput($process);
        if (! $this->needsFreeRadiusBootstrap($errorOutput)) {
            throw new ProcessFailedException($process);
        }

        $fallbackProcess = $this->attemptReloadFallback($command);
        if ($fallbackProcess !== null && $fallbackProcess->isSuccessful()) {
            return;
        }

        $detail = $fallbackProcess instanceof Process ? $this->processOutput($fallbackProcess) : $errorOutput;

        throw new RuntimeException(
            'Reload FreeRADIUS gagal karena akses sudo non-interaktif belum siap. Jalankan `bash install-selfhosted.sh deploy` sebagai root agar sudoers FreeRADIUS terpasang. Detail: '.$detail
        );
    }

    private function attemptReloadFallback(string $command): ?Process
    {
        $fallbackCommands = [];

        if ($this->filesystem->exists(self::SYNC_HELPER_PATH)) {
            $fallbackCommands[] = 'sudo -n '.self::SYNC_HELPER_PATH.' --reload-only';
        }

        if (! str_contains($command, 'sudo')) {
            $fallbackCommands[] = 'sudo -n systemctl reload freeradius';
        }

        $lastFailedProcess = null;

        foreach (array_values(array_unique($fallbackCommands)) as $fallbackCommand) {
            $fallbackProcess = $this->runShellCommand($fallbackCommand);
            if ($fallbackProcess->isSuccessful()) {
                return $fallbackProcess;
            }

            $lastFailedProcess = $fallbackProcess;
        }

        return $lastFailedProcess;
    }

    private function runShellCommand(string $command): Process
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        return $process;
    }

    private function processOutput(Process $process): string
    {
        return trim($process->getErrorOutput() ?: $process->getOutput());
    }

    private function needsFreeRadiusBootstrap(string $output): bool
    {
        $normalized = Str::lower($output);

        return str_contains($normalized, 'interactive authentication required')
            || str_contains($normalized, 'a terminal is required to read the password')
            || str_contains($normalized, 'password is required')
            || str_contains($normalized, 'no askpass program specified')
            || str_contains($normalized, 'command not found')
            || str_contains($normalized, 'not found');
    }

    private function buildClientsPayload(Collection $connections): string
    {
        $lines = [
            '# Generated by Laravel - do not edit manually',
        ];

        foreach ($connections as $connection) {
            $shortName = Str::slug($connection->name, '_') ?: 'mikrotik_'.$connection->id;
            $secret = addslashes($connection->radius_secret);

            $wgPeer = $connection->wgPeer;
            $viaWg  = $wgPeer?->is_active && $wgPeer->vpn_ip;
            $nasIp  = $viaWg ? $wgPeer->vpn_ip : $connection->host;
            $nasNote = $viaWg ? '# NAS IP via WireGuard tunnel' : '# NAS IP: direct (public)';
            $requireMsgAuth = $viaWg ? 'yes' : 'no';

            $lines[] = $nasNote;
            $lines[] = "client {$shortName} {";
            $lines[] = "    ipaddr = {$nasIp}";
            $lines[] = "    secret = {$secret}";
            $lines[] = "    shortname = {$shortName}";
            $lines[] = "    require_message_authenticator = {$requireMsgAuth}";
            $lines[] = '}';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
