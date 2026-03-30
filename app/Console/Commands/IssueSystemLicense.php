<?php

namespace App\Console\Commands;

use App\Services\LicenseIssuerService;
use Illuminate\Console\Command;
use RuntimeException;

class IssueSystemLicense extends Command
{
    protected $signature = 'license:issue
        {customer_name : Nama customer lisensi}
        {instance_name : Nama instance/deployment}
        {fingerprint : Fingerprint server target}
        {expires_at : Tanggal expired lisensi (YYYY-MM-DD)}
        {--license-id= : License ID kustom}
        {--issued-at= : Tanggal penerbitan lisensi (YYYY-MM-DD)}
        {--support-until= : Tanggal akhir support (YYYY-MM-DD)}
        {--grace-days= : Jumlah grace days}
        {--access-mode= : fingerprint_only, ip_based, domain_based, atau hybrid}
        {--domain=* : Domain yang diizinkan}
        {--host=* : Host/IP yang diizinkan}
        {--module=* : Modul aktif lisensi}
        {--limit=* : Limit dalam format key=value}
        {--path= : Simpan file lisensi ke path tertentu}
        {--json : Tampilkan payload lisensi sebagai JSON}';

    protected $description = 'Buat dan tanda tangani file lisensi sistem self-hosted dari server SaaS.';

    public function handle(LicenseIssuerService $licenseIssuerService): int
    {
        try {
            $payload = $licenseIssuerService->issue(
                customerName: (string) $this->argument('customer_name'),
                instanceName: (string) $this->argument('instance_name'),
                fingerprint: (string) $this->argument('fingerprint'),
                expiresAt: (string) $this->argument('expires_at'),
                allowedHosts: array_values(array_unique([
                    ...$this->stringArrayOption('domain'),
                    ...$this->stringArrayOption('host'),
                ])),
                modules: $this->stringArrayOption('module'),
                limits: $this->parseLimits(),
                licenseId: $this->stringOption('license-id'),
                issuedAt: $this->stringOption('issued-at'),
                supportUntil: $this->stringOption('support-until'),
                graceDays: $this->integerOption('grace-days'),
                accessMode: $this->stringOption('access-mode'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $this->stringOption('path');

        if ($path !== null) {
            $licenseIssuerService->store($payload, $path);
            $this->info('File lisensi berhasil dibuat.');
            $this->line('License ID  : '.$payload['license_id']);
            $this->line('Path        : '.$path);
        }

        if ($this->option('json') || $path === null) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function stringArrayOption(string $key): array
    {
        $value = $this->option($key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $value),
            fn (string $item): bool => $item !== ''
        ));
    }

    /**
     * @return array<string, int|string>
     */
    private function parseLimits(): array
    {
        $limits = [];

        foreach ($this->stringArrayOption('limit') as $limit) {
            if (! str_contains($limit, '=')) {
                throw new RuntimeException('Setiap --limit harus berformat key=value.');
            }

            [$key, $value] = explode('=', $limit, 2);
            $normalizedKey = trim($key);
            $normalizedValue = trim($value);

            if ($normalizedKey === '' || $normalizedValue === '') {
                throw new RuntimeException('Setiap --limit harus memiliki key dan value.');
            }

            $limits[$normalizedKey] = preg_match('/^-?\d+$/', $normalizedValue) === 1
                ? (int) $normalizedValue
                : $normalizedValue;
        }

        return $limits;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    private function integerOption(string $key): ?int
    {
        $value = $this->stringOption($key);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new RuntimeException("Nilai --{$key} harus berupa angka bulat.");
        }

        return (int) $value;
    }
}
