<?php

namespace App\Services;

use App\Models\OltConnection;
use RuntimeException;

/**
 * CLI client for HSGQ OLT via Telnet or SSH.
 *
 * Executes commands through the system's telnet/ssh binaries using proc_open.
 * Supports reading WiFi SSID/password from ONU and writing new values.
 */
class HsgqCliClient
{
    private const DEFAULT_TIMEOUT = 15;

    private const SSH_PORT_DEFAULT = 2211;

    private const TELNET_PORT_DEFAULT = 23;

    /**
     * Check if CLI is configured on this OLT connection.
     */
    public function isConfigured(OltConnection $oltConnection): bool
    {
        $protocol = trim((string) ($oltConnection->cli_protocol ?? 'none'));

        return in_array($protocol, ['telnet', 'ssh'], true)
            && filled($oltConnection->cli_username)
            && filled($oltConnection->cli_password);
    }

    /**
     * Run a single CLI command and return the output.
     *
     * @throws RuntimeException
     */
    public function runCommand(OltConnection $oltConnection, string $command): string
    {
        $protocol = trim((string) ($oltConnection->cli_protocol ?? 'none'));

        return match ($protocol) {
            'ssh' => $this->runSshCommand($oltConnection, $command),
            'telnet' => $this->runTelnetCommand($oltConnection, $command),
            default => throw new RuntimeException('Protokol CLI belum dikonfigurasi pada OLT ini. Pilih Telnet atau SSH di pengaturan OLT.'),
        };
    }

    /**
     * Get WiFi configuration (SSID + password) for a specific ONU.
     *
     * Returns array with keys: ssid, password, ssid2, password2 (nullable)
     *
     * @return array{ssid: string|null, password: string|null, ssid2: string|null, password2: string|null}
     *
     * @throws RuntimeException
     */
    public function getOnuWifiConfig(OltConnection $oltConnection, string $ponInterface, string $onuNumber): array
    {
        $pon = $this->normalizePon($ponInterface);
        $onu = $this->normalizeOnu($onuNumber);

        $output = $this->runCommand($oltConnection, "display ont wifi pon {$pon} ont {$onu}");

        return $this->parseWifiConfigOutput($output);
    }

    /**
     * Set WiFi SSID and password for a specific ONU.
     *
     * @throws RuntimeException
     */
    public function setOnuWifi(
        OltConnection $oltConnection,
        string $ponInterface,
        string $onuNumber,
        string $ssid,
        string $password
    ): void {
        $pon = $this->normalizePon($ponInterface);
        $onu = $this->normalizeOnu($onuNumber);

        $this->validateSsid($ssid);
        $this->validateWifiPassword($password);

        // Set SSID
        $this->runCommand($oltConnection, "set ont wifi ssid pon {$pon} ont {$onu} ssid {$ssid}");

        // Set password
        $this->runCommand($oltConnection, "set ont wifi password pon {$pon} ont {$onu} password {$password}");
    }

    /**
     * Reboot a specific ONU via CLI.
     *
     * @throws RuntimeException
     */
    public function rebootOnu(OltConnection $oltConnection, string $ponInterface, string $onuNumber): void
    {
        $pon = $this->normalizePon($ponInterface);
        $onu = $this->normalizeOnu($onuNumber);

        $this->runCommand($oltConnection, "reboot ont pon {$pon} ont {$onu}");
    }

    private function runSshCommand(OltConnection $oltConnection, string $command): string
    {
        $host = trim((string) $oltConnection->host);
        $port = (int) ($oltConnection->cli_port ?? self::SSH_PORT_DEFAULT);
        $username = (string) $oltConnection->cli_username;
        $password = (string) $oltConnection->cli_password;

        if ($host === '') {
            throw new RuntimeException('Host OLT tidak dikonfigurasi.');
        }

        // Use sshpass + ssh to run a single command non-interactively
        $sshArgs = implode(' ', [
            '-o StrictHostKeyChecking=no',
            '-o ConnectTimeout=10',
            '-o BatchMode=no',
            '-o UserKnownHostsFile=/dev/null',
            '-p '.escapeshellarg((string) $port),
        ]);

        $fullCommand = sprintf(
            'sshpass -p %s ssh %s %s@%s %s',
            escapeshellarg($password),
            $sshArgs,
            escapeshellarg($username),
            escapeshellarg($host),
            escapeshellarg($command)
        );

        return $this->execShellCommand($fullCommand, $host, $port);
    }

    private function runTelnetCommand(OltConnection $oltConnection, string $command): string
    {
        $host = trim((string) $oltConnection->host);
        $port = (int) ($oltConnection->cli_port ?? self::TELNET_PORT_DEFAULT);
        $username = (string) $oltConnection->cli_username;
        $password = (string) $oltConnection->cli_password;

        if ($host === '') {
            throw new RuntimeException('Host OLT tidak dikonfigurasi.');
        }

        // Use expect script for telnet interactive session
        $expectScript = $this->buildTelnetExpectScript($host, $port, $username, $password, $command);
        $tempScript = tempnam(sys_get_temp_dir(), 'hsgq_telnet_');

        if ($tempScript === false) {
            throw new RuntimeException('Gagal membuat temp file untuk skrip Telnet.');
        }

        try {
            file_put_contents($tempScript, $expectScript);
            chmod($tempScript, 0700);

            return $this->execShellCommand('expect '.$tempScript, $host, $port);
        } finally {
            @unlink($tempScript);
        }
    }

    private function buildTelnetExpectScript(
        string $host,
        int $port,
        string $username,
        string $password,
        string $command
    ): string {
        $escapedHost = addslashes($host);
        $escapedUsername = addslashes($username);
        $escapedPassword = addslashes($password);
        $escapedCommand = addslashes($command);

        return <<<EXPECT
        #!/usr/bin/expect -f
        set timeout 15
        spawn telnet {$escapedHost} {$port}
        expect {
            "Username:" { send "{$escapedUsername}\r" }
            "login:"    { send "{$escapedUsername}\r" }
            timeout     { exit 1 }
        }
        expect {
            "Password:" { send "{$escapedPassword}\r" }
            "password:" { send "{$escapedPassword}\r" }
            timeout     { exit 1 }
        }
        expect {
            "#" { }
            ">" { }
            timeout { exit 1 }
        }
        send "{$escapedCommand}\r"
        expect {
            "#" { }
            ">" { }
            timeout { exit 1 }
        }
        send "quit\r"
        expect eof
        EXPECT;
    }

    private function execShellCommand(string $command, string $host, int $port): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException("Gagal membuka proses CLI ke OLT {$host}:{$port}.");
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $errorDetail = trim($stderr ?: $stdout ?: '');
            $normalizedError = strtolower($errorDetail);

            if (str_contains($normalizedError, 'connection refused')) {
                throw new RuntimeException("Koneksi CLI ke OLT {$host}:{$port} ditolak. Pastikan port dan protokol benar.");
            }

            if (str_contains($normalizedError, 'no route') || str_contains($normalizedError, 'unreachable')) {
                throw new RuntimeException("OLT {$host} tidak dapat dijangkau via CLI.");
            }

            if (str_contains($normalizedError, 'permission denied') || str_contains($normalizedError, 'authentication failed')) {
                throw new RuntimeException('Autentikasi CLI ke OLT gagal. Periksa username dan password CLI.');
            }

            if ($errorDetail !== '') {
                throw new RuntimeException("CLI error: {$errorDetail}");
            }

            throw new RuntimeException("CLI ke OLT {$host}:{$port} gagal (exit code: {$exitCode}).");
        }

        return (string) $stdout;
    }

    /**
     * @return array{ssid: string|null, password: string|null, ssid2: string|null, password2: string|null}
     */
    private function parseWifiConfigOutput(string $output): array
    {
        $result = ['ssid' => null, 'password' => null, 'ssid2' => null, 'password2' => null];

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $ssidCount = 0;
        $passwordCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            // Match patterns like "SSID    : MyNetwork" or "ssid: MyNetwork"
            if (preg_match('/^ssid\s*[:\s]\s*(.+)$/i', $line, $matches)) {
                $value = trim($matches[1]);
                if ($ssidCount === 0) {
                    $result['ssid'] = $value;
                } elseif ($ssidCount === 1) {
                    $result['ssid2'] = $value;
                }
                $ssidCount++;

                continue;
            }

            // Match patterns like "Password : MyPass" or "password: MyPass"
            if (preg_match('/^(?:wifi[-_\s]?)?password\s*[:\s]\s*(.+)$/i', $line, $matches)) {
                $value = trim($matches[1]);
                if ($passwordCount === 0) {
                    $result['password'] = $value;
                } elseif ($passwordCount === 1) {
                    $result['password2'] = $value;
                }
                $passwordCount++;
            }
        }

        return $result;
    }

    private function normalizePon(string $ponInterface): string
    {
        // "PON1" → "1", "1" → "1"
        return ltrim(preg_replace('/^PON/i', '', trim($ponInterface)) ?? '', '0') ?: '0';
    }

    private function normalizeOnu(string $onuNumber): string
    {
        return ltrim(trim($onuNumber), '0') ?: '0';
    }

    private function validateSsid(string $ssid): void
    {
        $trimmed = trim($ssid);
        if ($trimmed === '') {
            throw new RuntimeException('SSID tidak boleh kosong.');
        }

        if (strlen($trimmed) > 32) {
            throw new RuntimeException('SSID maksimal 32 karakter.');
        }
    }

    private function validateWifiPassword(string $password): void
    {
        $trimmed = trim($password);
        if (strlen($trimmed) < 8) {
            throw new RuntimeException('Password WiFi minimal 8 karakter.');
        }

        if (strlen($trimmed) > 63) {
            throw new RuntimeException('Password WiFi maksimal 63 karakter.');
        }
    }
}
