<?php

namespace App\Console\Commands;

use App\Models\MikrotikConnection;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Console\Command;

class AuditIsolirReadiness extends Command
{
    protected $signature = 'billing:audit-isolir {--owner-id= : Audit tenant tertentu}';

    protected $description = 'Audit kesiapan isolir PPP dan captive portal dari konfigurasi aktif';

    public function handle(): int
    {
        $appUrl = trim((string) config('app.url', ''));
        $appHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?: $appUrl);
        $appScheme = strtolower((string) (parse_url($appUrl, PHP_URL_SCHEME) ?: 'http'));
        $isIpOnly = $appHost !== '' && filter_var($appHost, FILTER_VALIDATE_IP) !== false;

        $this->info('=== Audit Kesiapan Isolir ===');
        $this->line('APP_URL      : '.($appUrl !== '' ? $appUrl : '-'));
        $this->line('App Host     : '.($appHost !== '' ? $appHost : '-'));
        $this->line('Scheme       : '.strtoupper($appScheme));
        $this->line('Access Mode  : '.($isIpOnly ? 'ip-only' : 'domain/custom-host'));

        if ($isIpOnly) {
            $this->warn('Mode IP-only terdeteksi. Jalur utama captive portal yang direkomendasikan adalah HTTP port 80.');
        }

        if ($appScheme === 'https' && $isIpOnly) {
            $this->warn('APP_URL memakai HTTPS pada IP. Pastikan listener TLS by IP memang tersedia; jika tidak, captive portal lebih aman diuji via HTTP.');
        }

        $ownerId = $this->option('owner-id');
        $owners = User::query()
            ->where('role', 'administrator')
            ->when($ownerId, fn ($query) => $query->where('id', (int) $ownerId))
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        if ($owners->isEmpty()) {
            $this->warn('Tidak ada tenant administrator yang bisa diaudit.');

            return self::SUCCESS;
        }

        $readyTenants = 0;
        $warnTenants = 0;

        foreach ($owners as $owner) {
            $settings = TenantSettings::getOrCreate($owner->id);
            $connections = MikrotikConnection::query()
                ->where('owner_id', $owner->id)
                ->where('is_active', true)
                ->orderByDesc('is_online')
                ->get();

            $tenantWarnings = [];

            if (! $settings->auto_isolate_unpaid) {
                $tenantWarnings[] = 'auto_isolate_unpaid nonaktif';
            }

            if ($connections->isEmpty()) {
                $tenantWarnings[] = 'tidak ada NAS/MikroTik aktif';
            }

            $readyConnections = 0;

            foreach ($connections as $connection) {
                $missing = [];

                if (! filled($connection->isolir_pool_range)) {
                    $missing[] = 'pool_range';
                }

                if (! filled($connection->isolir_gateway)) {
                    $missing[] = 'gateway';
                }

                if (! filled($connection->isolir_profile_name)) {
                    $missing[] = 'profile_name';
                }

                if (! filled($connection->isolir_pool_name)) {
                    $missing[] = 'pool_name';
                }

                if (! filled($connection->isolir_rate_limit)) {
                    $missing[] = 'rate_limit';
                }

                if ($missing === []) {
                    $readyConnections++;
                    continue;
                }

                $tenantWarnings[] = sprintf(
                    'NAS "%s" belum lengkap: %s',
                    $connection->name,
                    implode(', ', $missing)
                );
            }

            if ($connections->isNotEmpty() && $readyConnections === 0) {
                $tenantWarnings[] = 'belum ada NAS yang siap untuk setup isolir';
            }

            $this->newLine();
            $this->line(sprintf(
                '[Tenant #%d] %s <%s>',
                $owner->id,
                $owner->name,
                $owner->email
            ));
            $this->line('  auto_isolate_unpaid : '.($settings->auto_isolate_unpaid ? 'ON' : 'OFF'));
            $this->line('  NAS aktif           : '.$connections->count());
            $this->line('  NAS siap isolir     : '.$readyConnections);
            $this->line('  Portal contact      : '.($settings->getIsolirPageContact() !== '' ? $settings->getIsolirPageContact() : '-'));

            if ($tenantWarnings === []) {
                $this->info('  Status              : READY');
                $readyTenants++;
                continue;
            }

            $this->warn('  Status              : NEEDS CHECK');
            foreach ($tenantWarnings as $warning) {
                $this->line('  - '.$warning);
            }
            $warnTenants++;
        }

        $this->newLine();
        $this->info(sprintf(
            'Ringkasan: %d tenant ready, %d tenant perlu pengecekan.',
            $readyTenants,
            $warnTenants
        ));

        if ($isIpOnly) {
            $this->line('Catatan: untuk mode ip-only, uji captive portal melalui HTTP/probe captive. HTTPS by IP tidak dijadikan indikator utama.');
        }

        return self::SUCCESS;
    }
}
