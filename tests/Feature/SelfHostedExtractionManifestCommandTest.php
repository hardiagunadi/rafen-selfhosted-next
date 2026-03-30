<?php

use App\Services\SelfHostedExtractionManifestService;
use Illuminate\Support\Facades\Artisan;

it('builds the self-hosted extraction manifest with expected cluster entries', function () {
    $manifest = app(SelfHostedExtractionManifestService::class)->build();

    expect($manifest)
        ->toBeArray()
        ->and($manifest['destination_repo'])->toBe('rafen-selfhosted')
        ->and($manifest['feature_flag'])->toBe('LICENSE_SELF_HOSTED_ENABLED')
        ->and($manifest['controllers'])->toContain('app/Http/Controllers/Controller.php')
        ->and($manifest['middleware'])->toContain('app/Http/Middleware/SuperAdminMiddleware.php')
        ->and($manifest['reference_tests'])->toContain('tests/Feature/SuperAdminSystemLicenseTest.php')
        ->and($manifest['services'])->toContain('app/Services/SystemLicenseService.php')
        ->and($manifest['routes'])->toContain('routes/self_hosted_license.php')
        ->and($manifest['post_extraction_cleanup'])->toContain(
            'bootstrap/providers.php: remove SelfHostedLicenseServiceProvider after the self-hosted repo is cut over'
        );
});

it('prints the self-hosted extraction manifest as json', function () {
    $exitCode = Artisan::call('self-hosted:manifest', [
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"destination_repo": "rafen-selfhosted"')
        ->and($output)->toContain('routes/self_hosted_license.php')
        ->and($output)->toContain('LICENSE_SELF_HOSTED_ENABLED');
});

it('prints the self-hosted extraction manifest in human readable form', function () {
    $this->artisan('self-hosted:manifest')
        ->expectsOutputToContain('Self-Hosted Extraction Manifest')
        ->expectsOutputToContain('Destination Repo : rafen-selfhosted')
        ->expectsOutputToContain('Services:')
        ->expectsOutputToContain('app/Services/SystemLicenseService.php')
        ->assertExitCode(0);
});
