<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('skips wireguard sync when the wg_peers table is not available yet', function () {
    Schema::dropIfExists('wg_peers');

    $this->artisan('wireguard:sync')
        ->expectsOutputToContain('Tabel wg_peers belum tersedia')
        ->assertSuccessful();
});

it('guards the installer when the wireguard sync command is unavailable', function () {
    $installerSource = file_get_contents(base_path('install-selfhosted.sh'));

    expect($installerSource)->toContain("grep -q '^wireguard:sync'")
        ->and($installerSource)->toContain('Command wireguard:sync belum tersedia');
});

it('ships deploy sudo bootstrap for follow-up deployments', function () {
    $installerSource = file_get_contents(base_path('install-selfhosted.sh'));

    expect($installerSource)
        ->toContain('DEPLOY_SUDOERS_PATH="${DEPLOY_SUDOERS_PATH:-/etc/sudoers.d/rafen-deploy}"')
        ->toContain('sudoers_content="$DEPLOY_USER ALL=(root) ALL"')
        ->toContain('write_deploy_sudoers')
        ->toContain('sudo \\')
        ->toContain("printf 'Deploy Sudoers       : %s\\n' \"\$DEPLOY_SUDOERS_PATH\"");
});

it('ships installer prompts for registry sync confirmation and super admin wa input', function () {
    $installerSource = file_get_contents(base_path('install-selfhosted.sh'));

    expect($installerSource)
        ->toContain('Aktifkan sinkronisasi install-time ke SaaS?')
        ->toContain('Nomor WhatsApp super admin awal')
        ->toContain('Nomor WhatsApp admin untuk sinkronisasi SaaS')
        ->toContain('Konfirmasi konfigurasi instalasi self-hosted')
        ->toContain('Registrasi install-time ke SaaS gagal. Instalasi lokal tetap dilanjutkan, tetapi instance ini belum tercatat otomatis di SaaS. Detail:');
});

it('ships a portable wireguard apply helper script', function () {
    $helperSource = file_get_contents(base_path('scripts/wireguard-apply.sh'));

    expect($helperSource)
        ->toContain('APP_DIR="${APP_DIR:-$(cd "${SCRIPT_DIR}/.." && pwd)}"')
        ->toContain('WG_SYSTEM_DIR="${WG_SYSTEM_DIR:-/etc/wireguard}"')
        ->toContain('WG_SYSTEM_INTERFACE="${WG_SYSTEM_INTERFACE:-wg0}"')
        ->toContain('WG_SYSTEM_SERVICE:-wg-quick@${WG_SYSTEM_INTERFACE}')
        ->not->toContain('/var/www/rafen-selfhosted-next/storage/app/wireguard/wg0.conf');
});
