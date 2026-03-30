<?php

namespace App\Services;

class SelfHostedLicenseViewDataService
{
    /**
     * @return array<string, mixed>
     */
    public function forAdminLayout(): array
    {
        $systemLicenseService = app(SystemLicenseService::class);
        $isSelfHostedLicenseEnabled = $systemLicenseService->isSelfHostedEnabled();

        return [
            'isSelfHostedLicenseEnabled' => $isSelfHostedLicenseEnabled,
            'systemLicenseSnapshot' => $isSelfHostedLicenseEnabled
                ? $systemLicenseService->getSnapshot()
                : null,
            'systemFeatureFlags' => $isSelfHostedLicenseEnabled
                ? app(FeatureGateService::class)->all()
                : $this->defaultFeatureFlags(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function defaultFeatureFlags(): array
    {
        return [
            'radius' => true,
            'vpn' => true,
            'wa' => true,
            'olt' => true,
            'genieacs' => true,
        ];
    }
}
