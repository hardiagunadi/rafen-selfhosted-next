<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckBaileysUpdate extends Command
{
    protected $signature = 'wa-gateway:check-baileys-update {--force : Paksa cek ulang meski cache masih berlaku}';

    protected $description = 'Cek versi terbaru baileys dari npm registry dan simpan ke cache';

    public function handle(): int
    {
        $packageJsonPath = base_path('wa-multi-session/package.json');

        if (! file_exists($packageJsonPath)) {
            $this->error('File wa-multi-session/package.json tidak ditemukan.');

            return self::FAILURE;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $currentVersion = $packageJson['dependencies']['baileys'] ?? null;
        $currentVersion = ltrim((string) $currentVersion, '^~');

        if (! $currentVersion) {
            $this->error('Versi baileys tidak ditemukan di package.json.');

            return self::FAILURE;
        }

        if (! $this->option('force') && Cache::has('baileys_update_check')) {
            $cached = Cache::get('baileys_update_check');
            $this->info("Cache masih berlaku. Versi terbaru: {$cached['latest_version']} (current: {$cached['current_version']})");

            return self::SUCCESS;
        }

        $this->info('Mengecek versi terbaru baileys dari npm registry...');

        try {
            $response = Http::timeout(15)->get('https://registry.npmjs.org/baileys');

            if (! $response->successful()) {
                $this->error('Gagal mengambil data dari npm registry.');

                return self::FAILURE;
            }

            $data = $response->json();
            $latestVersion = $data['dist-tags']['latest'] ?? null;
            $allVersions = array_keys($data['versions'] ?? []);

            // Filter hanya versi 7.x
            $v7Versions = array_filter($allVersions, fn ($v) => str_starts_with($v, '7.'));
            usort($v7Versions, 'version_compare');
            $latestV7 = end($v7Versions) ?: $latestVersion;

            $hasUpdate = version_compare($latestV7, $currentVersion, '>');

            $result = [
                'current_version'  => $currentVersion,
                'latest_version'   => $latestV7,
                'latest_stable'    => $latestVersion,
                'has_update'       => $hasUpdate,
                'checked_at'       => now()->toISOString(),
            ];

            // Simpan ke cache 12 jam
            Cache::put('baileys_update_check', $result, now()->addHours(12));

            if ($hasUpdate) {
                $this->warn("Update tersedia: {$currentVersion} → {$latestV7}");
            } else {
                $this->info("Baileys sudah versi terbaru: {$currentVersion}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::warning('CheckBaileysUpdate: gagal cek npm registry: '.$e->getMessage());
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
