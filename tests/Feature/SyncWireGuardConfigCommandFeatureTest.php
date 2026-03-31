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

it('ships a portable wireguard apply helper script', function () {
    $helperSource = file_get_contents(base_path('scripts/wireguard-apply.sh'));

    expect($helperSource)
        ->toContain('APP_DIR="${APP_DIR:-$(cd "${SCRIPT_DIR}/.." && pwd)}"')
        ->toContain('WG_SYSTEM_DIR="${WG_SYSTEM_DIR:-/etc/wireguard}"')
        ->toContain('WG_SYSTEM_INTERFACE="${WG_SYSTEM_INTERFACE:-wg0}"')
        ->toContain('WG_SYSTEM_SERVICE:-wg-quick@${WG_SYSTEM_INTERFACE}')
        ->not->toContain('/var/www/rafen-selfhosted-next/storage/app/wireguard/wg0.conf');
});
