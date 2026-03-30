<?php

use App\Services\SelfHostedWorkspaceAuditService;
use App\Services\SelfHostedWorkspaceSeedService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(storage_path('framework/self-hosted-workspace-audit-test'));
});

it('audits a seeded self-hosted workspace and reports missing app dependencies', function () {
    $target = storage_path('framework/self-hosted-workspace-audit-test');

    app(SelfHostedWorkspaceSeedService::class)->create($target, true);
    File::ensureDirectoryExists($target.'/vendor/acme/package');
    File::put($target.'/vendor/acme/package/Foo.php', <<<'PHP'
<?php

namespace Vendor\Acme;

use App\Missing\ShouldBeIgnored;

class Foo {}
PHP);

    $report = app(SelfHostedWorkspaceAuditService::class)->audit($target);

    expect($report['php_file_count'])->toBeGreaterThan(0)
        ->and($report['portable_runtime_php_file_count'])->toBeGreaterThan(0)
        ->and($report['portable_runtime_missing_dependency_count'])->toBe(0)
        ->and($report['test_missing_dependency_count'])->toBe(0)
        ->and($report['reference_missing_dependency_count'])->toBeGreaterThan(0)
        ->and($report['missing_dependency_count'])->toBeGreaterThan(0)
        ->and($report['reference_missing_dependencies'])->toHaveKey('App\\Providers\\AppServiceProvider')
        ->and($report['reference_missing_dependencies']['App\\Providers\\AppServiceProvider']['expected_path'])->toBe('app/Providers/AppServiceProvider.php')
        ->and($report['missing_dependencies'])->not->toHaveKey('App\\Missing\\ShouldBeIgnored');
});

it('prints the workspace audit as json', function () {
    $target = storage_path('framework/self-hosted-workspace-audit-test');

    app(SelfHostedWorkspaceSeedService::class)->create($target, true);

    $exitCode = Artisan::call('self-hosted:audit-workspace', [
        'target' => $target,
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"portable_runtime_missing_dependency_count"')
        ->and($output)->toContain('"test_missing_dependency_count": 0')
        ->and($output)->toContain('App\\\\Providers\\\\AppServiceProvider');
});

it('prints the workspace audit in human readable form', function () {
    $target = storage_path('framework/self-hosted-workspace-audit-test');

    app(SelfHostedWorkspaceSeedService::class)->create($target, true);

    $this->artisan("self-hosted:audit-workspace {$target}")
        ->expectsOutputToContain('Self-Hosted Workspace Audit')
        ->expectsOutputToContain('Portable Runtime Missing')
        ->expectsOutputToContain('Reference Missing')
        ->expectsOutputToContain('App\\Providers\\AppServiceProvider')
        ->assertExitCode(0);
});
