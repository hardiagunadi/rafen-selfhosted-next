<?php

use App\Services\SelfHostedExtractionStagingService;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(storage_path('framework/self-hosted-import-stage-test'));
    File::deleteDirectory(storage_path('framework/self-hosted-import-target-test'));
});

it('imports the portable self-hosted bundle into a target directory', function () {
    $stageDirectory = storage_path('framework/self-hosted-import-stage-test');
    $targetDirectory = storage_path('framework/self-hosted-import-target-test');

    config()->set('app.version', '2026.03.30-main.2');

    app(SelfHostedExtractionStagingService::class)->stage($stageDirectory, true);
    File::deleteDirectory($targetDirectory);

    $this->artisan("self-hosted:import {$stageDirectory} {$targetDirectory}")
        ->expectsOutputToContain('Portable self-hosted bundle berhasil diimport.')
        ->assertExitCode(0);

    expect(File::exists($targetDirectory.'/app/Services/SystemLicenseService.php'))->toBeTrue()
        ->and(File::exists($targetDirectory.'/app/Http/Controllers/Controller.php'))->toBeTrue()
        ->and(File::exists($targetDirectory.'/app/Http/Middleware/SuperAdminMiddleware.php'))->toBeTrue()
        ->and(File::exists($targetDirectory.'/routes/self_hosted_license.php'))->toBeTrue()
        ->and(File::exists($targetDirectory.'/wa-multi-session/dist/index.js'))->toBeTrue()
        ->and(File::exists($targetDirectory.'/.self-hosted-import.json'))->toBeTrue()
        ->and(File::exists($targetDirectory.'/_self_hosted_update_notice.json'))->toBeTrue();

    $updateNotice = json_decode((string) File::get($targetDirectory.'/_self_hosted_update_notice.json'), true);

    expect($updateNotice['available_version'] ?? null)->toBe('2026.03.30-main.2');
});

it('refuses to overwrite colliding files without force', function () {
    $stageDirectory = storage_path('framework/self-hosted-import-stage-test');
    $targetDirectory = storage_path('framework/self-hosted-import-target-test');

    app(SelfHostedExtractionStagingService::class)->stage($stageDirectory, true);

    File::ensureDirectoryExists($targetDirectory.'/app/Services');
    File::put($targetDirectory.'/app/Services/SystemLicenseService.php', 'collision');

    $this->artisan("self-hosted:import {$stageDirectory} {$targetDirectory}")
        ->expectsOutputToContain('File target sudah ada: app/Services/SystemLicenseService.php. Gunakan --force untuk menimpa.')
        ->assertExitCode(1);
});

it('overwrites colliding files when force is used', function () {
    $stageDirectory = storage_path('framework/self-hosted-import-stage-test');
    $targetDirectory = storage_path('framework/self-hosted-import-target-test');

    app(SelfHostedExtractionStagingService::class)->stage($stageDirectory, true);

    File::ensureDirectoryExists($targetDirectory.'/app/Services');
    File::put($targetDirectory.'/app/Services/SystemLicenseService.php', 'collision');

    $this->artisan("self-hosted:import {$stageDirectory} {$targetDirectory} --force")
        ->expectsOutputToContain('Portable self-hosted bundle berhasil diimport.')
        ->assertExitCode(0);

    expect(File::get($targetDirectory.'/app/Services/SystemLicenseService.php'))
        ->toContain('class SystemLicenseService');
});
