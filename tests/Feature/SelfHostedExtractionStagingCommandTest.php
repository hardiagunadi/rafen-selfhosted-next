<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(storage_path('framework/self-hosted-stage-test'));
});

it('stages the self-hosted extraction bundle into a target directory', function () {
    $target = storage_path('framework/self-hosted-stage-test');

    config()->set('app.version', '2026.03.30-main.1');

    File::deleteDirectory($target);

    $this->artisan("self-hosted:stage {$target}")
        ->expectsOutputToContain('Self-hosted extraction berhasil di-stage.')
        ->assertExitCode(0);

    expect(File::exists($target.'/manifest.json'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_update_notice.json'))->toBeTrue()
        ->and(File::exists($target.'/portable/app/Services/SystemLicenseService.php'))->toBeTrue()
        ->and(File::exists($target.'/portable/wa-multi-session/dist/index.js'))->toBeTrue()
        ->and(File::exists($target.'/portable/app/Http/Controllers/Controller.php'))->toBeTrue()
        ->and(File::exists($target.'/portable/app/Http/Middleware/SuperAdminMiddleware.php'))->toBeTrue()
        ->and(File::exists($target.'/portable/routes/self_hosted_license.php'))->toBeTrue()
        ->and(File::exists($target.'/references/bootstrap/app.php'))->toBeTrue()
        ->and(File::exists($target.'/references/routes/web.php'))->toBeTrue();

    $updateNotice = json_decode((string) File::get($target.'/_self_hosted_update_notice.json'), true);

    expect($updateNotice)
        ->toBeArray()
        ->and($updateNotice['available_version'] ?? null)->toBe('2026.03.30-main.1')
        ->and($updateNotice['manual_only'] ?? null)->toBeTrue();
});

it('refuses to stage into a non-empty directory without force', function () {
    $target = storage_path('framework/self-hosted-stage-test');

    File::ensureDirectoryExists($target);
    File::put($target.'/placeholder.txt', 'existing');

    $this->artisan("self-hosted:stage {$target}")
        ->expectsOutputToContain('Target directory staging sudah berisi file. Gunakan --force untuk menimpa.')
        ->assertExitCode(1);
});

it('overwrites an existing staging directory when force is used', function () {
    $target = storage_path('framework/self-hosted-stage-test');

    File::ensureDirectoryExists($target);
    File::put($target.'/placeholder.txt', 'existing');

    $this->artisan("self-hosted:stage {$target} --force")
        ->expectsOutputToContain('Self-hosted extraction berhasil di-stage.')
        ->assertExitCode(0);

    expect(File::exists($target.'/placeholder.txt'))->toBeFalse()
        ->and(File::exists($target.'/portable/app/Services/SystemLicenseService.php'))->toBeTrue();
});
