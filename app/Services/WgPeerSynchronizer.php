<?php

namespace App\Services;

use App\Models\WgPeer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class WgPeerSynchronizer
{
    public function __construct(private Filesystem $filesystem) {}

    /**
     * Rewrite wg0.conf with all peers and apply changes via wg syncconf.
     * Does not drop existing connections.
     *
     * @param  Collection<int, WgPeer>  $peers
     */
    public function syncAll(Collection $peers): void
    {
        $confPath = (string) config('wg.config_path');
        if ($confPath === '') {
            throw new RuntimeException('Path konfigurasi WireGuard belum diatur (WG_CONFIG_PATH).');
        }

        $directory = dirname($confPath);
        if (! $this->filesystem->isDirectory($directory)) {
            throw new RuntimeException("Direktori {$directory} belum ada. Jalankan install-wg.sh terlebih dahulu.");
        }

        if (! $this->filesystem->isWritable($directory)) {
            throw new RuntimeException("Direktori {$directory} tidak dapat ditulis oleh webserver.");
        }

        if ($this->filesystem->exists($confPath) && ! $this->filesystem->isWritable($confPath)) {
            throw new RuntimeException("File {$confPath} tidak dapat ditulis oleh webserver.");
        }

        $serverPrivateKey = (string) config('wg.server_private_key');
        if ($serverPrivateKey === '') {
            // Fallback: read from key file written by install-wg.sh
            $keyFile = '/etc/wireguard/server_private.key';
            if (is_readable($keyFile)) {
                $serverPrivateKey = trim((string) @file_get_contents($keyFile));
            }
        }
        if ($serverPrivateKey === '') {
            throw new RuntimeException('Server private key WireGuard belum diatur. Set WG_SERVER_PRIVATE_KEY di .env atau jalankan install-wg.sh.');
        }

        $payload = $this->buildConfig(
            $serverPrivateKey,
            (string) config('wg.server_address'),
            (string) config('wg.listen_port'),
            (string) config('wg.post_up'),
            (string) config('wg.post_down'),
            $peers
        );

        // Atomic write: write to temp file then rename
        $tmpPath = $confPath . '.tmp.' . getmypid();
        $this->filesystem->put($tmpPath, $payload);
        chmod($tmpPath, 0660);
        rename($tmpPath, $confPath);
        // Re-apply group ownership after rename (rename does not inherit permissions)
        @chgrp($confPath, 'www-data');

        // Apply changes without restarting the interface (no dropped connections)
        // wg syncconf memerlukan config tanpa PostUp/PostDown (stripped).
        // Pipe via /dev/stdin karena process substitution <(...) tidak kompatibel
        // dengan sudo. sudoers di /etc/sudoers.d/rafen-wireguard mengizinkan
        // www-data menjalankan kedua perintah tanpa password.
        $interface = (string) config('wg.interface', 'wg0');
        $iface     = escapeshellarg($interface);
        $command   = "sudo wg-quick strip {$iface} | sudo wg syncconf {$iface} /dev/stdin";

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            // In production throw; in local dev, WireGuard may not be installed
            if (app()->isProduction()) {
                throw new ProcessFailedException($process);
            }
        }
    }

    /**
     * @param  Collection<int, WgPeer>  $peers
     */
    private function buildConfig(
        string $serverPrivateKey,
        string $serverAddress,
        string $listenPort,
        string $postUp,
        string $postDown,
        Collection $peers
    ): string {
        $lines = [
            '# Managed by RAFEN — jangan diedit manual',
            '# Diperbarui: ' . now()->toDateTimeString(),
            '',
            '[Interface]',
            "PrivateKey = {$serverPrivateKey}",
            "Address = {$serverAddress}",
            "ListenPort = {$listenPort}",
        ];

        if ($postUp !== '') {
            $lines[] = "PostUp = {$postUp}";
        }
        if ($postDown !== '') {
            $lines[] = "PostDown = {$postDown}";
        }

        $activePeers = $peers->filter(
            fn (WgPeer $p) => $p->is_active && $p->public_key !== '' && $p->vpn_ip !== null
        );

        foreach ($activePeers as $peer) {
            $lines[] = '';
            $lines[] = "# Peer: {$peer->name}";
            $lines[] = '[Peer]';
            $lines[] = "PublicKey = {$peer->public_key}";
            if ($peer->preshared_key) {
                $lines[] = "PresharedKey = {$peer->preshared_key}";
            }
            $allowedIps = "{$peer->vpn_ip}/32";
            if ($peer->extra_allowed_ips) {
                $allowedIps .= ', '.trim($peer->extra_allowed_ips, ', ');
            }
            $lines[] = "AllowedIPs = {$allowedIps}";
        }

        $lines[] = '';
        return implode("\n", $lines);
    }
}
