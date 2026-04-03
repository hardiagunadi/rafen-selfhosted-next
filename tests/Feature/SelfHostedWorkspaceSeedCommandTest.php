<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(storage_path('framework/self-hosted-workspace-seed-test'));
});

it('creates a complete self-hosted workspace seed', function () {
    $target = storage_path('framework/self-hosted-workspace-seed-test');

    config()->set('app.version', '2026.03.30-main.3');

    File::deleteDirectory($target);

    $this->artisan("self-hosted:seed-workspace {$target}")
        ->expectsOutputToContain('Workspace seed self-hosted berhasil dibuat.')
        ->assertExitCode(0);

    expect(File::exists($target.'/app/Services/SystemLicenseService.php'))->toBeTrue()
        ->and(File::exists($target.'/.env.example'))->toBeTrue()
        ->and(File::exists($target.'/app/Models/User.php'))->toBeTrue()
        ->and(File::exists($target.'/wa-multi-session/dist/index.js'))->toBeTrue()
        ->and(File::exists($target.'/app/Http/Controllers/Controller.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Http/Controllers/Auth/LoginController.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Http/Middleware/SuperAdminMiddleware.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Http/Requests/Auth/LoginRequest.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Console/Commands/CreateInitialSuperAdmin.php'))->toBeTrue()
        ->and(File::exists($target.'/bootstrap/app.php'))->toBeTrue()
        ->and(File::exists($target.'/bootstrap/providers.php'))->toBeTrue()
        ->and(File::exists($target.'/database/factories/UserFactory.php'))->toBeTrue()
        ->and(File::exists($target.'/database/migrations/0001_01_01_000000_create_users_table.php'))->toBeTrue()
        ->and(File::exists($target.'/database/seeders/DatabaseSeeder.php'))->toBeTrue()
        ->and(File::exists($target.'/resources/views/auth/login.blade.php'))->toBeTrue()
        ->and(File::exists($target.'/routes/console.php'))->toBeTrue()
        ->and(File::exists($target.'/routes/web.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Pest.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/TestCase.php'))->toBeTrue()
        ->and(File::exists($target.'/resources/views/layouts/admin.blade.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Feature/SelfHostedBootstrapTest.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Feature/SuperAdminSystemLicenseTest.php'))->toBeFalse()
        ->and(File::exists($target.'/_integration-references/routes/web.php'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_manifest.json'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_cutover_plan.json'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_workspace_seed.json'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_update_notice.json'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_scaffold.json'))->toBeTrue();

    $updateNotice = json_decode((string) File::get($target.'/_self_hosted_update_notice.json'), true);

    expect($updateNotice['available_version'] ?? null)->toBe('2026.03.30-main.3');
});

it('refuses to create a workspace seed in a non-empty directory without force', function () {
    $target = storage_path('framework/self-hosted-workspace-seed-test');

    File::ensureDirectoryExists($target);
    File::put($target.'/placeholder.txt', 'existing');

    $this->artisan("self-hosted:seed-workspace {$target}")
        ->expectsOutputToContain('Target workspace sudah berisi file. Gunakan --force untuk membuat ulang.')
        ->assertExitCode(1);
});

it('recreates the workspace seed when force is used', function () {
    $target = storage_path('framework/self-hosted-workspace-seed-test');

    File::ensureDirectoryExists($target);
    File::put($target.'/placeholder.txt', 'existing');

    $this->artisan("self-hosted:seed-workspace {$target} --force")
        ->expectsOutputToContain('Workspace seed self-hosted berhasil dibuat.')
        ->assertExitCode(0);

    expect(File::exists($target.'/placeholder.txt'))->toBeFalse()
        ->and(File::exists($target.'/app/Services/SystemLicenseService.php'))->toBeTrue()
        ->and(File::exists($target.'/bootstrap/app.php'))->toBeTrue();
});
