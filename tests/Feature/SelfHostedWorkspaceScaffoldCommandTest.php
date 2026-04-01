<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(storage_path('framework/self-hosted-workspace-scaffold-test'));
});

it('creates a starter scaffold for a self-hosted workspace', function () {
    $target = storage_path('framework/self-hosted-workspace-scaffold-test');

    $this->artisan("self-hosted:scaffold-workspace {$target}")
        ->expectsOutputToContain('Scaffold workspace self-hosted berhasil dibuat.')
        ->assertExitCode(0);

    expect(File::exists($target.'/bootstrap/app.php'))->toBeTrue()
        ->and(File::exists($target.'/bootstrap/cache/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/.env.example'))->toBeTrue()
        ->and(File::get($target.'/.env.example'))->toContain('DB_CONNECTION=mariadb')
        ->and(File::get($target.'/.env.example'))->toContain('DB_USERNAME=rafen')
        ->and(File::get($target.'/.env.example'))->toContain('QUEUE_CONNECTION=database')
        ->and(File::exists($target.'/app/Models/User.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Http/Controllers/Auth/LoginController.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Http/Requests/Auth/LoginRequest.php'))->toBeTrue()
        ->and(File::exists($target.'/app/Console/Commands/CreateInitialSuperAdmin.php'))->toBeTrue()
        ->and(File::exists($target.'/bootstrap/providers.php'))->toBeTrue()
        ->and(File::exists($target.'/database/factories/UserFactory.php'))->toBeTrue()
        ->and(File::exists($target.'/database/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/database/migrations/0001_01_01_000000_create_users_table.php'))->toBeTrue()
        ->and(File::exists($target.'/database/seeders/DatabaseSeeder.php'))->toBeTrue()
        ->and(File::exists($target.'/resources/views/auth/login.blade.php'))->toBeTrue()
        ->and(File::exists($target.'/routes/console.php'))->toBeTrue()
        ->and(File::exists($target.'/routes/web.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Pest.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/TestCase.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Unit/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/storage/app/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/storage/app/license/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/storage/framework/cache/data/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/storage/framework/sessions/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/storage/framework/views/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/storage/logs/.gitignore'))->toBeTrue()
        ->and(File::exists($target.'/resources/views/layouts/admin.blade.php'))->toBeTrue()
        ->and(File::exists($target.'/tests/Feature/SelfHostedBootstrapTest.php'))->toBeTrue()
        ->and(File::exists($target.'/_self_hosted_scaffold.json'))->toBeTrue();
});

it('refuses to overwrite existing scaffold files without force', function () {
    $target = storage_path('framework/self-hosted-workspace-scaffold-test');

    File::ensureDirectoryExists($target.'/bootstrap');
    File::put($target.'/bootstrap/app.php', 'existing');

    $this->artisan("self-hosted:scaffold-workspace {$target}")
        ->expectsOutputToContain('File scaffold target sudah ada: bootstrap/app.php. Gunakan --force untuk menimpa.')
        ->assertExitCode(1);
});

it('overwrites scaffold files when force is used', function () {
    $target = storage_path('framework/self-hosted-workspace-scaffold-test');

    File::ensureDirectoryExists($target.'/bootstrap');
    File::put($target.'/bootstrap/app.php', 'existing');

    $this->artisan("self-hosted:scaffold-workspace {$target} --force")
        ->expectsOutputToContain('Scaffold workspace self-hosted berhasil dibuat.')
        ->assertExitCode(0);

    expect(File::get($target.'/bootstrap/app.php'))->toContain('Application::configure')
        ->and(File::exists($target.'/_self_hosted_scaffold.json'))->toBeTrue();
});
