<?php

use App\Services\SelfHostedCutoverPlanService;
use Illuminate\Support\Facades\Artisan;

it('builds the self-hosted cutover plan from the extraction manifest', function () {
    $plan = app(SelfHostedCutoverPlanService::class)->build();

    expect($plan)
        ->toBeArray()
        ->and($plan['source_repo'])->toBe('rafen-saas')
        ->and($plan['destination_repo'])->toBe('rafen-selfhosted')
        ->and($plan['saas_cleanup_candidates'])->toContain('app/Services/SystemLicenseService.php')
        ->and($plan['manual_patch_targets'])->toContain('routes/web.php')
        ->and($plan['verification_commands'])->toContain(
            'php artisan test --compact tests/Feature/SaasSelfHostedRouteIsolationTest.php'
        );
});

it('prints the self-hosted cutover plan as json', function () {
    $exitCode = Artisan::call('self-hosted:cutover-plan', [
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"source_repo": "rafen-saas"')
        ->and($output)->toContain('"destination_repo": "rafen-selfhosted"')
        ->and($output)->toContain('routes/web.php')
        ->and($output)->toContain('php artisan self-hosted:stage <target>');
});

it('prints the self-hosted cutover plan in human readable form', function () {
    $this->artisan('self-hosted:cutover-plan')
        ->expectsOutputToContain('Self-Hosted Cutover Plan')
        ->expectsOutputToContain('Source Repo      : rafen-saas')
        ->expectsOutputToContain('Destination Repo : rafen-selfhosted')
        ->expectsOutputToContain('Post Cutover Tasks:')
        ->assertExitCode(0);
});
