<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(storage_path('framework/self-hosted-repo-candidate-test'));
});

it('materializes a self-hosted repository candidate', function () {
    $target = storage_path('framework/self-hosted-repo-candidate-test');

    config()->set('app.version', '2026.03.30-main.4');

    $this->artisan("self-hosted:materialize-repo {$target}")
        ->expectsOutputToContain('Candidate repo self-hosted berhasil dibuat.')
        ->assertExitCode(0);

    expect(File::exists($target.'/.env.example'))->toBeTrue()
        ->and(File::exists($target.'/.editorconfig'))->toBeTrue()
        ->and(File::exists($target.'/artisan'))->toBeTrue()
        ->and(File::exists($target.'/composer.json'))->toBeTrue()
        ->and(File::exists($target.'/app/helpers.php'))->toBeTrue()
        ->and(File::exists($target.'/config/app.php'))->toBeTrue()
        ->and(File::exists($target.'/public/index.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Models/User.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Services/SystemLicenseService.php'))->toBeTrue()
        ->and(File::exists($target.'/wa-multi-session/dist/index.js'))->toBeTrue()
        ->and(File::exists($target.'/tests/Pest.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/TestCase.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Feature/SelfHostedBootstrapTest.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Feature/SuperAdminSystemLicenseTest.php'))->toBeFalse()
        ->and(File::exists($target.'/_self_hosted_update_notice.json'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_repository_candidate.json'))->toBeTrue();

    $updateNotice = json_decode((string) File::get($target.'/_self_hosted_update_notice.json'), true);

    expect($updateNotice['available_version'] ?? null)->toBe('2026.03.30-main.4');
});

it('refuses to materialize into a non empty directory without force', function () {
    $target = storage_path('framework/self-hosted-repo-candidate-test');

    File::ensureDirectoryExists($target);
    File::put($target.'/placeholder.txt', 'existing');

    $this->artisan("self-hosted:materialize-repo {$target}")
        ->expectsOutputToContain('Target repo sudah berisi file. Gunakan --force untuk membuat ulang.')
        ->assertExitCode(1);
});

it('recreates the candidate repository when force is used', function () {
    $target = storage_path('framework/self-hosted-repo-candidate-test');

    File::ensureDirectoryExists($target);
    File::put($target.'/placeholder.txt', 'existing');

    $this->artisan("self-hosted:materialize-repo {$target} --force")
        ->expectsOutputToContain('Candidate repo self-hosted berhasil dibuat.')
        ->assertExitCode(0);

    expect(File::exists($target.'/placeholder.txt'))->toBeFalse()
        ->and(File::exists($target.'/composer.json'))->toBeTrue()
        ->and(File::exists($target.'/app/Models/User.php'))->toBeTrue();
});
