<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class SelfHostedUpdateNoticeMetadataService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'schema' => 'self-hosted-update-notice:v1',
            'generated_at' => now()->toIso8601String(),
            'source_repo' => 'rafen',
            'available_version' => $this->version(),
            'headline' => 'Update Rafen Self-Hosted tersedia',
            'summary' => 'Versi self-hosted terbaru siap dipublikasikan. Sarankan pengguna menjadwalkan update manual di maintenance window agar layanan tidak terganggu.',
            'instructions' => 'Ambil backup database dan file penting, uji di staging atau server cadangan, lalu lakukan update saat jam maintenance yang aman.',
            'release_notes_url' => null,
            'severity' => 'warning',
            'manual_only' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function write(string $destinationPath): array
    {
        File::ensureDirectoryExists(dirname($destinationPath));

        $payload = $this->build();

        File::put($destinationPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    public function version(string $fallback = 'main-dev'): string
    {
        $version = trim((string) config('app.version', $fallback));

        return $version !== '' ? $version : $fallback;
    }
}
