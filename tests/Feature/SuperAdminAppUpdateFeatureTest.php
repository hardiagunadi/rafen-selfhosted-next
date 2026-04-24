<?php

use App\Models\SelfHostedUpdateRun;
use App\Models\SelfHostedUpdateState;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    putenv('APP_ENV=example');
    $_ENV['APP_ENV'] = 'example';
    $_SERVER['APP_ENV'] = 'example';
    putenv('APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
    $_ENV['APP_KEY'] = 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
    $_SERVER['APP_KEY'] = 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
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
    config()->set('license.enforce', true);
    config()->set('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
    config()->set('app.version', '2026.04.01-main.1');
    config()->set('app.commit', 'abc1234');
});

function createSuperAdminForAppUpdate(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

function createAppUpdateWorkdir(): string
{
    $path = sys_get_temp_dir().'/rafen-app-update-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($path.'/.git');

    return $path;
}

it('shows the app update page to self hosted super admin', function () {
    $superAdmin = createSuperAdminForAppUpdate();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.app-update'))
        ->assertSuccessful()
        ->assertSee('App Update')
        ->assertSee('Current Version')
        ->assertSee('Manifest update akan dicari otomatis dari release GitHub terbaru sesuai channel.')
        ->assertSee('Refresh Status')
        ->assertSee('Cek Update + Heartbeat')
        ->assertSee('Simulasi Apply')
        ->assertSee('Kirim Heartbeat Sekarang');
});

it('refreshes local snapshot without any network calls', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();

    SelfHostedUpdateState::query()->create([
        'channel' => 'stable',
        'current_version' => 'old-version',
        'current_commit' => 'oldcommit',
        'last_check_status' => 'never',
    ]);

    Http::fake();

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.refresh-status'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('success', 'Snapshot status lokal berhasil disegarkan tanpa panggilan network.');

    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($state)
        ->not->toBeNull()
        ->and($state?->current_version)->toBe('2026.04.01-main.1')
        ->and($state?->current_commit)->toBe('abc1234');

    Http::assertNothingSent();
});

it('checks update manifest from the app update page and stores update state', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();

    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-003');

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T09:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => true,
            'requires_backup' => true,
            'requires_migration' => false,
        ], 200),
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 42,
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.check'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('success', 'Cek update selesai. Release baru tersedia untuk instance ini.');

    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($state)
        ->not->toBeNull()
        ->and($state?->current_version)->toBe('2026.04.01-main.1')
        ->and($state?->current_commit)->toBe('abc1234')
        ->and($state?->latest_version)->toBe('2026.04.04-main.1')
        ->and($state?->latest_commit)->toBe('bee6dfb')
        ->and($state?->update_available)->toBeTrue()
        ->and($state?->last_check_status)->toBe('ok')
        ->and($state?->last_heartbeat_status)->toBe('success')
        ->and($state?->last_heartbeat_status_id)->toBe(42);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.app-update'))
        ->assertSuccessful()
        ->assertSee('2026.04.04-main.1')
        ->assertSee('bee6dfb')
        ->assertSee('Update Tersedia')
        ->assertSee('Buka Release Notes')
        ->assertSee('Sinkronisasi SaaS')
        ->assertSee('Heartbeat Terkonfigurasi')
        ->assertSee('SaaS Status ID')
        ->assertSee('42')
        ->assertSee('Heartbeat status instance berhasil dikirim ke SaaS.');
});

it('auto-discovers update manifest from github releases when manifest url is empty', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();

    config()->set('services.self_hosted_update.manifest_url', '');
    config()->set('services.self_hosted_update.repository', 'git@github.com:hardiagunadi/rafen-selfhosted-next.git');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-003');

    Http::fake([
        'https://api.github.com/repos/hardiagunadi/rafen-selfhosted-next/releases?per_page=10' => Http::response([
            [
                'tag_name' => 'v2026.04.04-main.1',
                'draft' => false,
                'prerelease' => false,
            ],
        ], 200),
        'https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.04-main.1/release-manifest.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T09:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => true,
            'requires_backup' => true,
            'requires_migration' => false,
        ], 200),
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 42,
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.check'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('success', 'Cek update selesai. Release baru tersedia untuk instance ini.');

    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($state)
        ->not->toBeNull()
        ->and($state?->latest_manifest_url)->toBe('https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/v2026.04.04-main.1/release-manifest.json')
        ->and($state?->last_check_status)->toBe('ok')
        ->and($state?->latest_version)->toBe('2026.04.04-main.1');
});

it('shows a clear error when the latest github release has no manifest asset', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();

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

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.check'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('error', 'Cek update selesai dengan status error: Auto-discovery release manifest gagal: Release GitHub v2026.04.04-main.2 ditemukan, tetapi asset release-manifest.json belum dipublikasikan.');

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.app-update'))
        ->assertSuccessful()
        ->assertSee('release-manifest.json belum dipublikasikan');
});

it('checks update and sends heartbeat explicitly from the app update page', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();

    config()->set('services.self_hosted_update.manifest_url', 'https://updates.example.test/releases/stable.json');
    config()->set('services.self_hosted_update.channel', 'stable');
    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-006');

    Http::fake([
        'https://updates.example.test/releases/stable.json' => Http::response([
            'schema' => 'rafen-self-hosted-release:v1',
            'channel' => 'stable',
            'version' => '2026.04.04-main.1',
            'tag' => 'v2026.04.04-main.1',
            'commit' => 'bee6dfb',
            'published_at' => '2026-04-04T09:00:00+07:00',
            'release_notes_url' => 'https://example.test/releases/v2026.04.04-main.1',
            'requires_maintenance' => true,
            'requires_backup' => false,
            'requires_migration' => false,
        ], 200),
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 91,
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.check-and-heartbeat'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('success', 'Cek update selesai. Release baru tersedia untuk instance ini. Heartbeat status instance berhasil dikirim ke SaaS.');

    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($state)
        ->not->toBeNull()
        ->and($state?->last_check_status)->toBe('ok')
        ->and($state?->last_heartbeat_status)->toBe('success')
        ->and($state?->last_heartbeat_status_id)->toBe(91);
});

it('runs dry run apply from the app update page and stores run history', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();
    $workdir = createAppUpdateWorkdir();

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
            'published_at' => '2026-04-04T09:00:00+07:00',
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
            default => Process::result('', 'Unexpected command in app update preflight test.', 1),
        };
    });

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.preflight'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('success', 'Simulasi apply selesai. Gunakan command CLI untuk menjalankan update aktual.');

    $run = SelfHostedUpdateRun::query()->latest('id')->first();
    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($run)
        ->not->toBeNull()
        ->and($run?->status)->toBe('dry_run')
        ->and($run?->action)->toBe('preflight');

    expect($state)
        ->not->toBeNull()
        ->and($state?->last_apply_status)->toBeNull()
        ->and($state?->last_applied_at)->toBeNull();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.app-update'))
        ->assertSuccessful()
        ->assertSee('Riwayat Run Update')
        ->assertSee('DRY_RUN')
        ->assertSee('v2026.04.04-main.1');
});

it('shows stale heartbeat indicator when the last successful sync is too old', function () {
    Carbon::setTestNow('2026-04-04 12:00:00');

    $superAdmin = createSuperAdminForAppUpdate();

    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-004');
    config()->set('services.self_hosted_registry.heartbeat_stale_after_minutes', 60);

    SelfHostedUpdateState::query()->create([
        'channel' => 'stable',
        'current_version' => '2026.04.01-main.1',
        'current_commit' => 'abc1234',
        'last_heartbeat_at' => now()->subMinutes(95),
        'last_successful_heartbeat_at' => now()->subMinutes(95),
        'last_heartbeat_status' => 'success',
        'last_heartbeat_message' => 'Heartbeat status instance berhasil dikirim ke SaaS.',
        'last_heartbeat_status_id' => 77,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.app-update'))
        ->assertSuccessful()
        ->assertSee('Heartbeat Stale')
        ->assertSee('Perlu Sinkronisasi')
        ->assertSee('Heartbeat sukses terakhir lebih lama dari 60 menit.')
        ->assertSee('77');

    Carbon::setTestNow();
});

it('shows self hosted update attention summary in the navbar notification dropdown', function () {
    Carbon::setTestNow('2026-04-04 12:00:00');

    $superAdmin = createSuperAdminForAppUpdate();

    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-007');
    config()->set('services.self_hosted_registry.heartbeat_stale_after_minutes', 60);

    SelfHostedUpdateState::query()->create([
        'channel' => 'stable',
        'current_version' => '2026.04.01-main.1',
        'current_commit' => 'abc1234',
        'latest_version' => '2026.04.04-main.1',
        'update_available' => true,
        'last_apply_status' => 'failed',
        'last_apply_message' => 'Apply terakhir gagal.',
        'last_heartbeat_at' => now()->subMinutes(95),
        'last_successful_heartbeat_at' => now()->subMinutes(95),
        'last_heartbeat_status' => 'success',
        'last_heartbeat_message' => 'Heartbeat status instance berhasil dikirim ke SaaS.',
    ]);

    $response = $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.app-update'))
        ->assertSuccessful()
        ->assertSee('Update Self-Hosted Tersedia')
        ->assertSee('Apply Update Gagal')
        ->assertSee('Heartbeat Self-Hosted Stale');

    expect($response->getContent())
        ->toContain('nav-icon-badge badge-warning">3<')
        ->toContain(route('super-admin.settings.app-update'));

    Carbon::setTestNow();
});

it('sends heartbeat manually from the app update page', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = createSuperAdminForAppUpdate();

    config()->set('services.self_hosted_registry.url', 'https://saas.example.test/api/self-hosted/install-registrations');
    config()->set('services.self_hosted_registry.token', 'registry-token-005');

    Http::fake([
        'https://saas.example.test/api/self-hosted/heartbeats' => Http::response([
            'status_id' => 88,
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.settings.app-update.heartbeat'))
        ->assertRedirect(route('super-admin.settings.app-update'))
        ->assertSessionHas('success', 'Heartbeat status instance berhasil dikirim ke SaaS.');

    $state = SelfHostedUpdateState::query()->where('channel', 'stable')->first();

    expect($state)
        ->not->toBeNull()
        ->and($state?->last_heartbeat_status)->toBe('success')
        ->and($state?->last_heartbeat_status_id)->toBe(88);
});
