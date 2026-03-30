<?php

namespace App\Services;

class FeatureGateService
{
    public function __construct(
        private readonly SystemLicenseService $systemLicenseService,
    ) {}

    public function isEnabled(string $feature): bool
    {
        if (! $this->systemLicenseService->isSelfHostedEnabled()) {
            return true;
        }

        if (! $this->systemLicenseService->isEnforced()) {
            return true;
        }

        $license = $this->systemLicenseService->getCurrent();

        if (! $license->is_valid) {
            return false;
        }

        $modules = collect($license->modules ?? [])
            ->filter(fn (mixed $module): bool => is_string($module) && $module !== '')
            ->values()
            ->all();

        $requiredModules = $this->requiredModules($feature);

        if ($requiredModules === []) {
            return true;
        }

        foreach ($requiredModules as $module) {
            if (in_array($module, $modules, true)) {
                return true;
            }
        }

        return false;
    }

    public function message(string $feature): string
    {
        return 'Fitur '.$this->label($feature).' tidak termasuk dalam lisensi sistem aktif.';
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return [
            'radius' => $this->isEnabled('radius'),
            'vpn' => $this->isEnabled('vpn'),
            'wa' => $this->isEnabled('wa'),
            'olt' => $this->isEnabled('olt'),
            'genieacs' => $this->isEnabled('genieacs'),
        ];
    }

    /**
     * @return list<string>
     */
    private function requiredModules(string $feature): array
    {
        return match ($feature) {
            'radius' => ['radius'],
            'vpn' => ['vpn'],
            'wa' => ['wa'],
            'olt' => ['olt'],
            'genieacs' => ['genieacs'],
            default => [],
        };
    }

    private function label(string $feature): string
    {
        return match ($feature) {
            'radius' => 'FreeRADIUS',
            'vpn' => 'WireGuard / VPN',
            'wa' => 'WhatsApp Gateway',
            'olt' => 'OLT',
            'genieacs' => 'GenieACS / CPE',
            default => $feature,
        };
    }
}
