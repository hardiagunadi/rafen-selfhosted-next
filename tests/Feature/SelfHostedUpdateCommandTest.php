<?php

use App\Models\SelfHostedUpdateRun;
use App\Models\SelfHostedUpdateState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    putenv('APP_ENV=example');
    $_ENV['APP_ENV'] = 'example';
    $_SERVER['APP_ENV'] = 'example';
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=:memory:');
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = ':memory:';
    $_SERVER['DB_CONNECTION'] = 'sqlite';
    $_SERVER['DB_DATABASE'] = ':memory:';
    putenv('LICENSE_SELF_HOSTED_ENABLED=true');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'true';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'true';

    $this->refreshApplication();
    $this->artisan('migrate');

    config()->set('license.self_hosted_enabled', true);
    config()->set('app.version', '2026.04.01-main.1');
    config()->set('app.commit', 'abc1234');
});

function createSelfHostedUpdateWorkdir(): string
{
    $path = sys_get_temp_dir().'/rafen-self-hosted-update-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($path.'/.git');
    File::put($path.'/.env', "APP_VERSION=main-dev\nAPP_COMMIT=\n");

    return $path;
}

it('checks self hosted update manifest from artisan command', function () {
    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-002');

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.01-main.1',
            'tag' => 'v2026.04.01-main.1',
            'commit' => 'abc1234',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.01-main.1',
            'requires_maintenance' => false,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 7,
        ], 200),
    ]);

    $this->artisan('self-hosted:update:check')
        ->expectsOutputToContain('Current Version  : 2026.04.01-main.1')
        ->expectsOutputToContain('Latest Version   : 2026.04.01-main.1')
        ->expectsOutputToContain('Check Status     : ok')
        ->expectsOutputToContain('Instance sudah menggunakan release terbaru.')
        ->assertSuccessful();

    expect(SelfHostedUpdateState::query()->where('channel', 'stable')->first())
        ->not->toBeNull()
        ->and(SelfHostedUpdateState::query()->where('channel', 'stable')->value('update_available'))->toBeFalse();

    Http::assertSent(fn ($request) => $request->url() === 'https://saas.example.test/api/self-hosted/heartbeats');
});

it('auto-discovers self hosted update manifest from github releases', function () {
    config()->set('services.self_hosted_update.manifest_url', '');
    config()->set('services.self_hosted_update.repository', 'git@github.com:hardiagunadi/rafen-selfhosted-next.git');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-002');

    Http::fake([
        'https://api.github.com/repos/hardiagunadi/rafen-selfhosted-next/releases?per_page=10' => Http::response([
            [
                'tag_name' => 'v2026.04.01-main.1',
                'draft' => false,
                'prerelease' => false,
            ],
        ], 200),
        'https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.01-main.1/release-manifest.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.01-main.1',
            'tag' => 'v2026.04.01-main.1',
            'commit' => 'abc1234',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.01-main.1',
            'requires_maintenance' => false,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 7,
        ], 200),
    ]);

    $this->artisan('self-hosted:update:check')
        ->expectsOutputToContain('Manifest URL     : https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.01-main.1/release-manifest.json')
        ->expectsOutputToContain('Check Status     : ok')
        ->assertSuccessful();

    expect(SelfHostedUpdateState::query()->where('channel', 'stable')->value('latest_manifest_url'))
        ->toBe('https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.01-main.1/release-manifest.json');
});

it('prefers the github release manifest asset url during auto-discovery', function () {
    config()->set('services.self_hosted_update.manifest_url', '');
    config()->set('services.self_hosted_update.repository', 'git@github.com:hardiagunadi/rafen-selfhosted-next.git');
    config()->set('services.self_hosted_update.channel', 'stable');

    Http::fake([
        'https://api.github.com/repos/hardiagunadi/rafen-selfhosted-next/releases?per_page=10' => Http::response([
            [
                'tag_name' => 'v2026.04.04-main.2',
                'draft' => false,
                'prerelease' => false,
                'html_url' => 'https://github.com/hardiagunadi/rafen-selfhosted-next/releases/tag/v2026.04.04-main.2',
                'assets' => [
                    [
                        'name' => 'release-manifest.json',
                        'browser_download_url' => 'https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.04-main.2/release-manifest.json?raw=1',
                    ],
                ],
            ],
        ], 200),
        'https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.04-main.2/release-manifest.json?raw=1' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.2',
            'tag' => 'v2026.04.04-main.2',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T12:00:00+07:00',
            'requires_maintenance' => false,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
    ]);

    $this->artisan('self-hosted:update:check')
        ->expectsOutputToContain('Manifest URL     : https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.04-main.2/release-manifest.json?raw=1')
        ->expectsOutputToContain('Check Status     : ok')
        ->assertSuccessful();

    expect(SelfHostedUpdateState::query()->where('channel', 'stable')->value('latest_manifest_url'))
        ->toBe('https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.04-main.2/release-manifest.json?raw=1');
});

it('reports a clear error when the latest github release has no manifest asset', function () {
    config()->set('services.self_hosted_update.manifest_url', '');
    config()->set('services.self_hosted_update.repository', 'git@github.com:hardiagunadi/rafen-selfhosted-next.git');
    config()->set('services.self_hosted_update.channel', 'stable');

    Http::fake([
        'https://api.github.com/repos/hardiagunadi/rafen-selfhosted-next/releases?per_page=10' => Http::response([
            [
                'tag_name' => 'v2026.04.04-main.2',
                'draft' => false,
                'prerelease' => false,
                'assets' => [],
            ],
        ], 200),
    ]);

    $this->artisan('self-hosted:update:check')
        ->expectsOutputToContain('Check Status     : error')
        ->expectsOutputToContain('Auto-discovery release manifest gagal: Release GitHub v2026.04.04-main.2 ditemukan, tetapi asset release-manifest.json belum dipublikasikan.')
        ->assertFailed();
});

it('shows a more informative current version when app version is still main-dev', function () {
    $workdir = createSelfHostedUpdateWorkdir();

    config()->set('app.version', 'main-dev');
    config()->set('app.commit', 'abc1234');
    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_update.workdir', $workdir);

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'requires_maintenance' => false,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
    ]);

    $this->artisan('self-hosted:update:check')
        ->expectsOutputToContain('Current Version  : main-dev+abc1234')
        ->assertSuccessful();
});

it('runs self hosted apply dry run and stores an audit trail', function () {
    $workdir = createSelfHostedUpdateWorkdir();

    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_update.workdir', $workdir);

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => true,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
    ]);

    Process::fake(function ($process) {
        return match ($process->command) {
            'git rev-parse HEAD' => Process::result('1111111111111111111111111111111111111111', '', 0),
            'git status --short --untracked-files=normal' => Process::result('', '', 0),
            default => Process::result('', 'Unexpected command in dry-run apply test.', 1),
        };
    });

    $this->artisan('self-hosted:update:apply --dry-run')
        ->expectsOutputToContain('Status           : dry_run')
        ->expectsOutputToContain('Target Version   : 2026.04.04-main.1')
        ->assertSuccessful();

    $run = SelfHostedUpdateRun::query()->latest('id')->first();
    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($run)
        ->not->toBeNull()
        ->and($run?->status)->toBe('dry_run')
        ->and($run?->action)->toBe('preflight')
        ->and($run?->target_ref)->toBe('v2026.04.04-main.1')
        ->and($run?->rollback_ref)->toBe('1111111111111111111111111111111111111111')
        ->and($state?->last_apply_status)->toBeNull()
        ->and($state?->last_applied_at)->toBeNull();
});

it('applies self hosted update from artisan command', function () {
    $workdir = createSelfHostedUpdateWorkdir();

    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_update.workdir', $workdir);

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => true,
            'requires_backup' => false,
            'requires_migration' => true,
        ], 200),
    ]);

    $php = escapeshellarg(PHP_BINARY);

    Process::fake(function ($process) use ($php) {
        $command = $process->command;

        return match (true) {
            $command === 'git rev-parse HEAD' => Process::result('1111111111111111111111111111111111111111', '', 0),
            $command === 'git status --short --untracked-files=normal' => Process::result('', '', 0),
            $command === $php.' artisan down --retry=60' => Process::result('', '', 0),
            $command === 'git fetch --tags origin' => Process::result('', '', 0),
            $command === "git checkout --detach 'v2026.04.04-main.1'" => Process::result('', '', 0),
            $command === 'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader' => Process::result('composer ok', '', 0),
            $command === $php.' artisan migrate --force' => Process::result('migrated', '', 0),
            $command === $php.' artisan optimize:clear' => Process::result('', '', 0),
            $command === $php.' artisan config:cache' => Process::result('', '', 0),
            $command === $php.' artisan route:cache' => Process::result('', '', 0),
            $command === $php.' artisan view:cache' => Process::result('', '', 0),
            $command === $php.' artisan up' => Process::result('', '', 0),
            default => Process::result('', 'Unexpected command in apply test.', 1),
        };
    });

    $this->artisan('self-hosted:update:apply --yes')
        ->expectsOutputToContain('Status           : success')
        ->expectsOutputToContain('Target Ref       : v2026.04.04-main.1')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === 'git fetch --tags origin');
    Process::assertRan(fn ($process) => str_contains($process->command, 'artisan down --retry=60'));
    Process::assertRan(fn ($process) => str_contains($process->command, 'artisan up'));

    $run = SelfHostedUpdateRun::query()->latest('id')->first();
    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($run)
        ->not->toBeNull()
        ->and($run?->status)->toBe('success')
        ->and($run?->target_commit)->toBe('bee6dfb')
        ->and($state?->last_apply_status)->toBe('success')
        ->and($state?->current_version)->toBe('2026.04.04-main.1')
        ->and($state?->current_ref)->toBe('v2026.04.04-main.1');

    expect(File::get($workdir.'/.env'))
        ->toContain('APP_VERSION=2026.04.04-main.1')
        ->toContain('APP_COMMIT=bee6dfb');
});

it('accepts php artisan-prefixed post update commands from manifest', function () {
    $workdir = createSelfHostedUpdateWorkdir();

    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_update.workdir', $workdir);

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => true,
            'requires_backup' => false,
            'requires_migration' => false,
            'post_update' => [
                'artisan' => [
                    'php artisan optimize:clear',
                    'php artisan config:cache',
                ],
            ],
        ], 200),
    ]);

    $php = escapeshellarg(PHP_BINARY);

    Process::fake(function ($process) use ($php) {
        $command = $process->command;

        return match (true) {
            $command === 'git rev-parse HEAD' => Process::result('1111111111111111111111111111111111111111', '', 0),
            $command === 'git status --short --untracked-files=normal' => Process::result('', '', 0),
            $command === $php.' artisan down --retry=60' => Process::result('', '', 0),
            $command === 'git fetch --tags origin' => Process::result('', '', 0),
            $command === "git checkout --detach 'v2026.04.04-main.1'" => Process::result('', '', 0),
            $command === 'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader' => Process::result('composer ok', '', 0),
            $command === $php.' artisan optimize:clear' => Process::result('', '', 0),
            $command === $php.' artisan config:cache' => Process::result('', '', 0),
            $command === $php.' artisan up' => Process::result('', '', 0),
            default => Process::result('', 'Unexpected command in post update manifest test.', 1),
        };
    });

    $this->artisan('self-hosted:update:apply --yes')
        ->expectsOutputToContain('Status           : success')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === $php.' artisan optimize:clear');
    Process::assertRan(fn ($process) => $process->command === $php.' artisan config:cache');
});

it('skips maintenance mode when manifest does not require it', function () {
    $workdir = createSelfHostedUpdateWorkdir();

    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_update.workdir', $workdir);

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T10:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => false,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
    ]);

    $php = escapeshellarg(PHP_BINARY);

    Process::fake(function ($process) use ($php) {
        $command = $process->command;

        return match (true) {
            $command === 'git rev-parse HEAD' => Process::result('1111111111111111111111111111111111111111', '', 0),
            $command === 'git status --short --untracked-files=normal' => Process::result('', '', 0),
            $command === 'git fetch --tags origin' => Process::result('', '', 0),
            $command === "git checkout --detach 'v2026.04.04-main.1'" => Process::result('', '', 0),
            $command === 'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader' => Process::result('composer ok', '', 0),
            $command === $php.' artisan optimize:clear' => Process::result('', '', 0),
            $command === $php.' artisan config:cache' => Process::result('', '', 0),
            $command === $php.' artisan route:cache' => Process::result('', '', 0),
            $command === $php.' artisan view:cache' => Process::result('', '', 0),
            default => Process::result('', 'Unexpected command in no-maintenance apply test.', 1),
        };
    });

    $this->artisan('self-hosted:update:apply --yes')
        ->expectsOutputToContain('Status           : success')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === 'git fetch --tags origin');
    Process::assertNotRan(fn ($process) => str_contains($process->command, 'artisan down --retry=60'));
    Process::assertNotRan(fn ($process) => str_contains($process->command, 'artisan up'));
});
