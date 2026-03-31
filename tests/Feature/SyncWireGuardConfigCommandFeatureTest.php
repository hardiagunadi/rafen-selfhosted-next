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
