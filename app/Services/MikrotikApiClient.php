<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use RuntimeException;

class MikrotikApiClient
{
    /**
     * @var resource|null
     */
    private $stream = null;

    public function __construct(private MikrotikConnection $connection) {}

    public function connect(): void
    {
        if ($this->stream) {
            return;
        }

        $host = trim((string) $this->connection->host);
        if ($host === '') {
            throw new RuntimeException('Host Mikrotik belum diisi.');
        }

        $timeout = max(1, (int) ($this->connection->api_timeout ?: 10));
        $port = $this->connection->use_ssl
            ? ($this->connection->api_ssl_port ?: 8729)
            : ($this->connection->api_port ?: 8728);
        $scheme = $this->connection->use_ssl ? 'ssl' : 'tcp';
        $context = $this->connection->use_ssl
            ? stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ])
            : null;

        if ($context) {
            $stream = @stream_socket_client(
                $scheme.'://'.$host.':'.$port,
                $errorNumber,
                $errorMessage,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context,
            );
        } else {
            $stream = @stream_socket_client(
                $scheme.'://'.$host.':'.$port,
                $errorNumber,
                $errorMessage,
                $timeout,
                STREAM_CLIENT_CONNECT,
            );
        }

        if (! $stream) {
            throw new RuntimeException("Gagal terhubung ke Mikrotik: {$errorMessage} ({$errorNumber}).");
        }

        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;

        $this->login();
    }

    public function disconnect(): void
    {
        if (! $this->stream) {
            return;
        }

        fclose($this->stream);
        $this->stream = null;
    }

    /**
     * @param  array<string, string|int>  $attributes
     * @param  array<string, string|int>  $queries
     * @return array{data: array<int, array<string, string>>, done: array<string, string>}
     */
    public function command(string $path, array $attributes = [], array $queries = []): array
    {
        $this->ensureConnected();

        return $this->sendCommand($path, $attributes, $queries);
    }

    private function ensureConnected(): void
    {
        if (! $this->stream) {
            $this->connect();
        }
    }

    /**
     * @param  array<string, string|int>  $attributes
     * @param  array<string, string|int>  $queries
     * @return array{data: array<int, array<string, string>>, done: array<string, string>}
     */
    private function sendCommand(string $path, array $attributes = [], array $queries = []): array
    {
        if (! $this->stream) {
            throw new RuntimeException('Koneksi Mikrotik belum siap.');
        }

        $words = [$path];

        foreach ($queries as $key => $value) {
            $words[] = '?'.$key.'='.$value;
        }

        foreach ($attributes as $key => $value) {
            $words[] = '='.$key.'='.$value;
        }

        $this->writeSentence($words);

        return $this->readResponse();
    }

    private function login(): void
    {
        $username = trim((string) $this->connection->username);
        $password = (string) $this->connection->password;

        if ($username === '' || $password === '') {
            throw new RuntimeException('Username atau password Mikrotik belum diisi.');
        }

        $response = $this->sendCommand('/login', [
            'name' => $username,
            'password' => $password,
        ]);

        $token = $response['done']['ret'] ?? null;
        if (! $token) {
            return;
        }

        $hash = md5("\0".$password.pack('H*', $token));

        $this->sendCommand('/login', [
            'name' => $username,
            'response' => '00'.$hash,
        ]);
    }

    /**
     * @param  array<int, string>  $words
     */
    private function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord($word);
        }

        $this->writeWord('');
    }

    private function writeWord(string $word): void
    {
        $this->writeLength(strlen($word));

        if ($word !== '') {
            fwrite($this->stream, $word);
        }
    }

    private function writeLength(int $length): void
    {
        if ($length < 0x80) {
            fwrite($this->stream, chr($length));

            return;
        }

        if ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($this->stream, chr(($length >> 8) & 0xFF).chr($length & 0xFF));

            return;
        }

        if ($length < 0x200000) {
            $length |= 0xC00000;
            fwrite(
                $this->stream,
                chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF),
            );

            return;
        }

        if ($length < 0x10000000) {
            $length |= 0xE0000000;
            fwrite(
                $this->stream,
                chr(($length >> 24) & 0xFF)
                .chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF)
                .chr($length & 0xFF),
            );

            return;
        }

        fwrite(
            $this->stream,
            chr(0xF0)
            .chr(($length >> 24) & 0xFF)
            .chr(($length >> 16) & 0xFF)
            .chr(($length >> 8) & 0xFF)
            .chr($length & 0xFF),
        );
    }

    /**
     * @return array{data: array<int, array<string, string>>, done: array<string, string>}
     */
    private function readResponse(): array
    {
        $data = [];
        $done = [];

        while (true) {
            $sentence = $this->readSentence();
            if ($sentence === []) {
                continue;
            }

            $type = array_shift($sentence);
            $attributes = $this->parseSentenceAttributes($sentence);

            if ($type === '!re') {
                $data[] = $attributes;

                continue;
            }

            if ($type === '!trap' || $type === '!fatal') {
                $message = $attributes['message'] ?? 'Perintah Mikrotik gagal.';

                throw new RuntimeException($message);
            }

            if ($type === '!done') {
                $done = $attributes;
                break;
            }
        }

        return ['data' => $data, 'done' => $done];
    }

    /**
     * @param  array<int, string>  $sentence
     * @return array<string, string>
     */
    private function parseSentenceAttributes(array $sentence): array
    {
        $attributes = [];

        foreach ($sentence as $word) {
            if (! str_starts_with($word, '=')) {
                continue;
            }

            $parts = explode('=', $word, 3);
            if (count($parts) < 3) {
                continue;
            }

            $attributes[$parts[1]] = $parts[2];
        }

        return $attributes;
    }

    /**
     * @return array<int, string>
     */
    private function readSentence(): array
    {
        $words = [];

        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }

            $words[] = $word;
        }

        return $words;
    }

    private function readWord(): string
    {
        $length = $this->readLength();
        if ($length === 0) {
            return '';
        }

        $data = $this->readBytes($length);

        return $data;
    }

    private function readLength(): int
    {
        $byte = ord($this->readBytes(1));

        if (($byte & 0x80) === 0x00) {
            return $byte;
        }

        if (($byte & 0xC0) === 0x80) {
            $byte2 = ord($this->readBytes(1));

            return (($byte & 0x3F) << 8) + $byte2;
        }

        if (($byte & 0xE0) === 0xC0) {
            $byte2 = ord($this->readBytes(1));
            $byte3 = ord($this->readBytes(1));

            return (($byte & 0x1F) << 16) + ($byte2 << 8) + $byte3;
        }

        if (($byte & 0xF0) === 0xE0) {
            $byte2 = ord($this->readBytes(1));
            $byte3 = ord($this->readBytes(1));
            $byte4 = ord($this->readBytes(1));

            return (($byte & 0x0F) << 24) + ($byte2 << 16) + ($byte3 << 8) + $byte4;
        }

        $byte2 = ord($this->readBytes(1));
        $byte3 = ord($this->readBytes(1));
        $byte4 = ord($this->readBytes(1));
        $byte5 = ord($this->readBytes(1));

        return ($byte2 << 24) + ($byte3 << 16) + ($byte4 << 8) + $byte5;
    }

    private function readBytes(int $length): string
    {
        if (! $this->stream) {
            throw new RuntimeException('Koneksi Mikrotik belum siap.');
        }

        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($this->stream, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                if (($meta['timed_out'] ?? false) === true) {
                    throw new RuntimeException('Koneksi Mikrotik timeout.');
                }

                throw new RuntimeException('Gagal membaca data dari Mikrotik.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
