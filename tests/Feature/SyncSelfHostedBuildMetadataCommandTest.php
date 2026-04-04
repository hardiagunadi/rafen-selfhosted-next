<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

afterEach(function (): void {
    File::delete(storage_path('framework/sync-build-metadata-test.env'));
});

it('syncs build metadata into an env file from the artisan command', function () {
    config()->set('app.version', 'main-dev');
    config()->set('app.commit', '');

    Process::fake([
        'git describe --tags --exact-match HEAD' => Process::result('v2026.04.06-main.1', '', 0),
        'git rev-parse --short HEAD' => Process::result('c0ffee1', '', 0),
    ]);

    $target = storage_path('framework/sync-build-metadata-test.env');

    File::put($target, "APP_VERSION=main-dev\nAPP_COMMIT=\n");

    $this->artisan("self-hosted:sync-build-metadata --env-path={$target}")
        ->expectsOutputToContain('Metadata build self-hosted berhasil disinkronkan.')
        ->expectsOutputToContain('APP_VERSION       : 2026.04.06-main.1')
        ->expectsOutputToContain('APP_COMMIT        : c0ffee1')
        ->assertSuccessful();

    expect(File::get($target))
        ->toContain('APP_VERSION=2026.04.06-main.1')
        ->toContain('APP_COMMIT=c0ffee1');
});
