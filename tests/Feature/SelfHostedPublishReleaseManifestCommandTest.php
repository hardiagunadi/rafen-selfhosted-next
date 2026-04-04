<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

afterEach(function (): void {
    File::delete(storage_path('framework/release-manifest-test.json'));
});

it('publishes a self hosted release manifest from the current git tag', function () {
    config()->set('app.version', 'main-dev');
    config()->set('app.commit', '');
    config()->set('services.self_hosted_update.repository', 'git@github.com:hardiagunadi/rafen-selfhosted-next.git');

    Process::fake([
        'git describe --tags --exact-match HEAD' => Process::result('v2026.04.04-main.2', '', 0),
        'git rev-parse --short HEAD' => Process::result('45a215f', '', 0),
        'node --version' => Process::result('v22.14.0', '', 0),
    ]);

    $target = storage_path('framework/release-manifest-test.json');

    $this->artisan("self-hosted:publish-release-manifest {$target} --published-at=2026-04-04T15:54:29+07:00")
        ->expectsOutputToContain('Release manifest self-hosted berhasil dipublikasikan.')
        ->expectsOutputToContain('Version           : 2026.04.04-main.2')
        ->expectsOutputToContain('Tag               : v2026.04.04-main.2')
        ->expectsOutputToContain('Commit            : 45a215f')
        ->assertSuccessful();

    $payload = json_decode((string) File::get($target), true);

    expect($payload)
        ->toBeArray()
        ->and($payload['schema'] ?? null)->toBe('rafen-self-hosted-release:v1')
        ->and($payload['version'] ?? null)->toBe('2026.04.04-main.2')
        ->and($payload['tag'] ?? null)->toBe('v2026.04.04-main.2')
        ->and($payload['commit'] ?? null)->toBe('45a215f')
        ->and($payload['release_notes_url'] ?? null)->toBe('https://github.com/hardiagunadi/rafen-selfhosted-next/releases/tag/v2026.04.04-main.2')
        ->and($payload['requires_maintenance'] ?? null)->toBeTrue()
        ->and($payload['requires_backup'] ?? null)->toBeTrue()
        ->and($payload['requires_migration'] ?? null)->toBeTrue()
        ->and($payload['node_major'] ?? null)->toBe(22)
        ->and(data_get($payload, 'package.ref'))->toBe('v2026.04.04-main.2')
        ->and(data_get($payload, 'post_update.artisan'))->toBe([
            'php artisan optimize:clear',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
        ]);
});

it('accepts explicit overrides and prints the json payload', function () {
    config()->set('services.self_hosted_update.repository', 'https://github.com/hardiagunadi/rafen-selfhosted-next');

    Process::fake([
        'node --version' => Process::result('', 'node not installed', 1),
    ]);

    $target = storage_path('framework/release-manifest-test.json');

    $this->artisan("self-hosted:publish-release-manifest {$target} --tag=v2026.04.05-main.1 --release-version=2026.04.05-main.1 --commit=abc1234 --published-at=2026-04-05T07:00:00+07:00 --without-maintenance --without-backup --without-migration --post-update='php artisan horizon:terminate' --post-update='optimize:clear' --minimum-supported-from=2026.04.01-main.1 --php-version=8.4 --node-major=22 --json")
        ->expectsOutputToContain('"version": "2026.04.05-main.1"')
        ->assertSuccessful();

    $payload = json_decode((string) File::get($target), true);

    expect($payload)
        ->toBeArray()
        ->and($payload['requires_maintenance'] ?? null)->toBeFalse()
        ->and($payload['requires_backup'] ?? null)->toBeFalse()
        ->and($payload['requires_migration'] ?? null)->toBeFalse()
        ->and($payload['minimum_supported_from'] ?? null)->toBe('2026.04.01-main.1')
        ->and($payload['php_version'] ?? null)->toBe('8.4')
        ->and($payload['node_major'] ?? null)->toBe(22)
        ->and(data_get($payload, 'post_update.artisan'))->toBe([
            'php artisan horizon:terminate',
            'php artisan optimize:clear',
        ]);
});
