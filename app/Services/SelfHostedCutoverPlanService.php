<?php

namespace App\Services;

class SelfHostedCutoverPlanService
{
    public function __construct(
        private readonly SelfHostedExtractionManifestService $manifestService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $manifest = $this->manifestService->build();

        return [
            'source_repo' => 'rafen-saas',
            'destination_repo' => $manifest['destination_repo'],
            'feature_flag' => $manifest['feature_flag'],
            'preflight_checks' => [
                'Pastikan branch SaaS dalam kondisi hijau sebelum file self-hosted dipindah.',
                'Jalankan php artisan self-hosted:manifest untuk review daftar file dan touchpoint terakhir.',
                'Jalankan php artisan self-hosted:stage <target> untuk membuat bundle staging yang akan diimport ke repo baru.',
                'Pastikan private signing key tetap tidak pernah masuk ke repo SaaS atau bundle self-hosted.',
            ],
            'stage_and_import_steps' => [
                'Copy seluruh isi portable/ dari hasil staging ke repo rafen-selfhosted.',
                'Import references/ sebagai bahan merge manual untuk bootstrap/app, providers, routes, dan layout di repo baru.',
                'Aktifkan provider self-hosted, middleware alias, dan routes/self_hosted_license.php di repo baru.',
                'Pastikan env LICENSE_SELF_HOSTED_ENABLED, LICENSE_ENFORCE, LICENSE_PUBLIC_KEY, dan LICENSE_PUBLIC_KEY_EDITABLE tersedia di repo baru.',
            ],
            'saas_cleanup_candidates' => $manifest['portable_files'],
            'manual_patch_targets' => $manifest['integration_touchpoints'],
            'post_cutover_tasks' => [
                'Hapus cluster self-hosted dari repo SaaS setelah repo baru lulus smoke test.',
                'Buang include routes/self_hosted_license.php dari routes/web.php di repo SaaS.',
                'Lepas SelfHostedLicenseServiceProvider dari bootstrap/providers.php di repo SaaS.',
                'Hapus alias system.license dan system.feature jika middleware self-hosted tidak lagi dikirim bersama SaaS.',
                'Copot partial self-hosted dari layouts.admin jika UI lisensi tidak lagi ditampilkan di repo SaaS.',
            ],
            'verification_commands' => [
                'php artisan test --compact tests/Feature/SaasSelfHostedRouteIsolationTest.php',
                'php artisan test --compact tests/Feature/SuperAdminTenantEditRoleListTest.php',
                'vendor/bin/pint --dirty --format agent',
            ],
            'self_hosted_repo_verification' => [
                'php artisan test --compact tests/Feature/SuperAdminSystemLicenseTest.php',
                'php artisan test --compact tests/Feature/SystemLicenseStageTwoTest.php',
            ],
        ];
    }
}
