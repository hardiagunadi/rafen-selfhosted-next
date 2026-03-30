<?php

use App\Models\User;
use App\Services\SelfHostedToolkitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.self_hosted_toolkit_ignore_dirty_worktree', true);
});

function makeToolkitSuperAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeToolkitTenantAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

afterEach(function (): void {
    File::delete(storage_path('framework/self-hosted-toolkit/last-runs.json'));
    File::delete(storage_path('framework/self-hosted-update-notice-ui/_self_hosted_update_notice.json'));
    File::delete(storage_path('framework/self-hosted-toolkit/downloads/materialize_repo.zip'));
});

it('allows super admin to open the self hosted toolkit page', function () {
    $superAdmin = makeToolkitSuperAdmin();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.self-hosted-toolkit.index'))
        ->assertSuccessful()
        ->assertSee('Self-Hosted Toolkit')
        ->assertSee('Manifest')
        ->assertSee('Materialize Repo')
        ->assertSee('Publish Update Notice')
        ->assertSee('Download')
        ->assertSee('Belum ada artifact');
});

it('blocks tenant admin from opening the self hosted toolkit page', function () {
    $tenantAdmin = makeToolkitTenantAdmin();

    $this->actingAs($tenantAdmin)
        ->get(route('super-admin.self-hosted-toolkit.index'))
        ->assertForbidden();
});

it('runs a toolkit action for super admin and stores last output', function () {
    $superAdmin = makeToolkitSuperAdmin();
    config()->set('app.version', '2026.03.30-main.9');

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.self-hosted-toolkit.run'), [
            'action' => 'publish_update_notice',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('result.action', 'publish_update_notice')
        ->assertJsonPath('result.exit_code', 0);

    $history = json_decode((string) File::get(storage_path('framework/self-hosted-toolkit/last-runs.json')), true);

    expect($history)
        ->toBeArray()
        ->and($history['publish_update_notice']['action'] ?? null)->toBe('publish_update_notice')
        ->and($history['publish_update_notice']['success'] ?? null)->toBeTrue()
        ->and(File::exists(storage_path('framework/self-hosted-update-notice-ui/_self_hosted_update_notice.json')))->toBeTrue();
});

it('rejects invalid toolkit action', function () {
    $superAdmin = makeToolkitSuperAdmin();

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.self-hosted-toolkit.run'), [
            'action' => 'drop_database',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['action']);
});

it('blocks release toolkit action when main repo worktree is dirty', function () {
    config()->set('app.self_hosted_toolkit_ignore_dirty_worktree', false);

    $superAdmin = makeToolkitSuperAdmin();

    $this->partialMock(SelfHostedToolkitService::class, function ($mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->makePartial();
        $mock->shouldReceive('dirtyWorktreeEntries')
            ->andReturn(['M app/Services/GenieAcsClient.php']);
    });

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.self-hosted-toolkit.run'), [
            'action' => 'publish_update_notice',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'Worktree repo utama masih kotor. Commit/stash perubahan SaaS dan self-hosted dulu sebelum menjalankan aksi rilis ini.',
        ]);
});

it('shows dirty worktree warning in toolkit page', function () {
    config()->set('app.self_hosted_toolkit_ignore_dirty_worktree', false);

    $superAdmin = makeToolkitSuperAdmin();

    $this->partialMock(SelfHostedToolkitService::class, function ($mock): void {
        $mock->makePartial();
        $mock->shouldReceive('dirtyWorktreeEntries')
            ->andReturn([
                'M app/Services/GenieAcsClient.php',
                '?? app/Services/SelfHostedToolkitService.php',
            ]);
    });

    $this->actingAs($superAdmin)
        ->get(route('super-admin.self-hosted-toolkit.index'))
        ->assertSuccessful()
        ->assertSee('Worktree repo utama masih kotor')
        ->assertSee('GenieAcsClient.php');
});

it('returns json error when toolkit fails unexpectedly', function () {
    $superAdmin = makeToolkitSuperAdmin();

    $this->mock(SelfHostedToolkitService::class, function ($mock): void {
        $mock->shouldReceive('run')
            ->once()
            ->with('manifest')
            ->andThrow(new Exception('proc_open unavailable'));
    });

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.self-hosted-toolkit.run'), [
            'action' => 'manifest',
        ])
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Toolkit gagal dijalankan: proc_open unavailable',
        ]);
});

it('downloads publish update notice artifact for super admin', function () {
    $superAdmin = makeToolkitSuperAdmin();
    config()->set('app.version', '2026.03.30-main.12');

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.self-hosted-toolkit.run'), [
            'action' => 'publish_update_notice',
        ])
        ->assertSuccessful();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.self-hosted-toolkit.download', 'publish_update_notice'))
        ->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename=_self_hosted_update_notice.json');
});

it('downloads directory artifact as zip for super admin', function () {
    $superAdmin = makeToolkitSuperAdmin();
    $directory = storage_path('framework/self-hosted-repo-ui');

    File::ensureDirectoryExists($directory);
    File::put($directory.'/README.txt', 'repo artifact');

    $this->actingAs($superAdmin)
        ->get(route('super-admin.self-hosted-toolkit.download', 'materialize_repo'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('content-disposition', 'attachment; filename=materialize_repo.zip');
});

it('shows artifact ready status after toolkit artifact is generated', function () {
    $superAdmin = makeToolkitSuperAdmin();
    config()->set('app.version', '2026.03.30-main.13');

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.self-hosted-toolkit.run'), [
            'action' => 'publish_update_notice',
        ])
        ->assertSuccessful();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.self-hosted-toolkit.index'))
        ->assertSuccessful()
        ->assertSee('Artifact ready');
});

it('still runs toolkit action when history persistence is not writable', function () {
    File::partialMock()
        ->shouldReceive('exists')
        ->once()
        ->with(storage_path('framework/self-hosted-toolkit/last-runs.json'))
        ->andReturn(false);

    File::shouldReceive('ensureDirectoryExists')
        ->once()
        ->with(dirname(storage_path('framework/self-hosted-toolkit/last-runs.json')))
        ->andThrow(new RuntimeException('permission denied'));

    $result = app(SelfHostedToolkitService::class)->run('manifest');

    expect($result['success'])->toBeTrue()
        ->and($result['exit_code'])->toBe(0);
});
