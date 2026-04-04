<?php

use App\Services\AppBuildMetadataService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

afterEach(function (): void {
    File::delete(storage_path('framework/app-build-metadata-test.env'));
});

it('syncs app build metadata from git into the env file', function () {
    config()->set('app.version', 'main-dev');
    config()->set('app.commit', '');

    Process::fake([
        'git describe --tags --exact-match HEAD' => Process::result('v2026.04.05-main.1', '', 0),
        'git rev-parse --short HEAD' => Process::result('45a215f', '', 0),
    ]);

    $envPath = storage_path('framework/app-build-metadata-test.env');

    File::put($envPath, "APP_NAME=\"Rafen Self-Hosted\"\nAPP_VERSION=main-dev\nAPP_COMMIT=\n");

    $result = app(AppBuildMetadataService::class)->syncEnvFile($envPath);

    expect($result['version'])->toBe('2026.04.05-main.1')
        ->and($result['commit'])->toBe('45a215f')
        ->and(File::get($envPath))->toContain('APP_VERSION=2026.04.05-main.1')
        ->and(File::get($envPath))->toContain('APP_COMMIT=45a215f');
});

it('keeps explicit version overrides when syncing the env file', function () {
    Process::fake([
        'git describe --tags --exact-match HEAD' => Process::result('', 'not tagged', 1),
        'git rev-parse --short HEAD' => Process::result('abc1234', '', 0),
    ]);

    $envPath = storage_path('framework/app-build-metadata-test.env');

    File::put($envPath, "APP_VERSION=main-dev\nAPP_COMMIT=\n");

    $result = app(AppBuildMetadataService::class)->syncEnvFile($envPath, '2026.04.06-main.1', 'bee6dfb');

    expect($result['version'])->toBe('2026.04.06-main.1')
        ->and($result['commit'])->toBe('bee6dfb')
        ->and(File::get($envPath))->toContain('APP_VERSION=2026.04.06-main.1')
        ->and(File::get($envPath))->toContain('APP_COMMIT=bee6dfb');
});
