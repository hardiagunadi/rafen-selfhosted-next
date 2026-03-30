<?php

namespace App\Services;

class SelfHostedExtractionManifestService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $manifest = [
            'destination_repo' => 'rafen-selfhosted',
            'feature_flag' => 'LICENSE_SELF_HOSTED_ENABLED',
            'env_vars' => [
                'LICENSE_SELF_HOSTED_ENABLED',
                'LICENSE_ENFORCE',
                'LICENSE_PUBLIC_KEY',
                'LICENSE_PUBLIC_KEY_EDITABLE',
                'LICENSE_FILE_PATH',
                'LICENSE_MACHINE_ID_PATH',
                'LICENSE_DEFAULT_GRACE_DAYS',
            ],
            'config' => [
                'config/license.php',
            ],
            'providers' => [
                'app/Providers/SelfHostedLicenseServiceProvider.php',
                'bootstrap/providers.php',
            ],
            'routes' => [
                'routes/self_hosted_license.php',
            ],
            'controllers' => [
                'app/Http/Controllers/Controller.php',
                'app/Http/Controllers/SuperAdminLicenseController.php',
            ],
            'requests' => [
                'app/Http/Requests/UploadSystemLicenseRequest.php',
            ],
            'middleware' => [
                'app/Http/Middleware/EnsureValidSystemLicense.php',
                'app/Http/Middleware/EnsureSystemFeatureEnabled.php',
                'app/Http/Middleware/SuperAdminMiddleware.php',
                'bootstrap/app.php',
            ],
            'models' => [
                'app/Models/SystemLicense.php',
            ],
            'services' => [
                'app/Services/SystemLicenseService.php',
                'app/Services/FeatureGateService.php',
                'app/Services/LicenseActivationRequestService.php',
                'app/Services/LicenseFingerprintService.php',
                'app/Services/LicenseSignatureService.php',
                'app/Services/SelfHostedLicenseViewDataService.php',
            ],
            'commands' => [
                'app/Console/Commands/ShowSystemLicenseStatus.php',
                'app/Console/Commands/RefreshSystemLicense.php',
                'app/Console/Commands/GenerateSystemLicenseActivationRequest.php',
            ],
            'views' => [
                'resources/views/super-admin/settings/license.blade.php',
                'resources/views/self-hosted-license/partials/admin-nav-item.blade.php',
                'resources/views/self-hosted-license/partials/admin-alert.blade.php',
            ],
            'database' => [
                'database/migrations/2026_03_29_173527_create_system_licenses_table.php',
            ],
            'reference_tests' => [
                'tests/Feature/SuperAdminSystemLicenseTest.php',
                'tests/Feature/SystemLicenseStageTwoTest.php',
            ],
            'post_extraction_cleanup' => [
                'routes/web.php: remove conditional require for routes/self_hosted_license.php once the cluster is moved',
                'bootstrap/providers.php: remove SelfHostedLicenseServiceProvider after the self-hosted repo is cut over',
                'bootstrap/app.php: remove system.license and system.feature aliases if SaaS no longer ships the self-hosted middleware',
                'resources/views/layouts/admin.blade.php: remove self-hosted partial includes and fallback state once SaaS no longer exposes those controls',
            ],
            'retain_in_saas' => [
                'app/Providers/AppServiceProvider.php',
                'routes/web.php',
                'resources/views/layouts/admin.blade.php',
            ],
        ];

        $manifest['integration_touchpoints'] = [
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'app/Providers/AppServiceProvider.php',
            'routes/web.php',
            'resources/views/layouts/admin.blade.php',
        ];
        $manifest['portable_files'] = $this->portableFiles($manifest);

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function portableFiles(array $manifest): array
    {
        $portableGroups = [
            'config',
            'providers',
            'routes',
            'controllers',
            'requests',
            'middleware',
            'models',
            'services',
            'commands',
            'views',
            'database',
        ];

        $files = [];

        foreach ($portableGroups as $group) {
            foreach ($manifest[$group] as $path) {
                if (! in_array($path, $manifest['integration_touchpoints'], true)) {
                    $files[] = $path;
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }
}
