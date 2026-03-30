<?php

namespace App\Http\Requests;

use App\Services\LicenseIssuerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueSelfHostedLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'license_preset' => ['nullable', 'string', Rule::in(app(LicenseIssuerService::class)->presetKeys())],
            'access_mode' => ['nullable', 'string', Rule::in(['fingerprint_only', 'ip_based', 'domain_based', 'hybrid'])],
            'customer_name' => ['required', 'string', 'max:255'],
            'instance_name' => ['required', 'string', 'max:255'],
            'fingerprint' => ['required', 'string', 'regex:/^sha256:[a-f0-9]{64}$/'],
            'expires_at' => ['required', 'date_format:Y-m-d'],
            'support_until' => ['nullable', 'date_format:Y-m-d'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(['core', 'mikrotik', 'radius', 'vpn', 'wa', 'olt', 'genieacs'])],
            'allowed_hosts_text' => ['nullable', 'string', 'max:5000'],
            'max_mikrotik' => ['nullable', 'integer', 'min:1'],
            'max_ppp_users' => ['nullable', 'integer', 'min:1'],
            'additional_limits' => ['nullable', 'json'],
        ];
    }

    public function messages(): array
    {
        return [
            'license_preset.in' => 'Preset lisensi yang dipilih tidak dikenali.',
            'access_mode.in' => 'Mode akses lisensi tidak dikenali.',
            'customer_name.required' => 'Nama customer wajib diisi.',
            'instance_name.required' => 'Nama instance wajib diisi.',
            'fingerprint.required' => 'Fingerprint server target wajib diisi.',
            'fingerprint.regex' => 'Fingerprint harus berupa hash sha256 yang valid.',
            'expires_at.required' => 'Tanggal berlaku sampai wajib diisi.',
            'expires_at.date_format' => 'Tanggal berlaku sampai harus berformat YYYY-MM-DD.',
            'support_until.date_format' => 'Tanggal support sampai harus berformat YYYY-MM-DD.',
            'grace_days.integer' => 'Grace days harus berupa angka.',
            'grace_days.min' => 'Grace days minimal 0.',
            'modules.array' => 'Daftar modul tidak valid.',
            'modules.*.in' => 'Ada modul lisensi yang tidak dikenali.',
            'allowed_hosts_text.max' => 'Daftar host/IP terlalu panjang.',
            'max_mikrotik.integer' => 'Limit MikroTik harus berupa angka.',
            'max_mikrotik.min' => 'Limit MikroTik minimal 1.',
            'max_ppp_users.integer' => 'Limit PPP users harus berupa angka.',
            'max_ppp_users.min' => 'Limit PPP users minimal 1.',
            'additional_limits.json' => 'Limit tambahan harus berupa JSON object yang valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'license_preset' => trim((string) $this->input('license_preset', '')) ?: null,
            'access_mode' => trim((string) $this->input('access_mode', '')) ?: null,
            'customer_name' => trim((string) $this->input('customer_name', '')),
            'instance_name' => trim((string) $this->input('instance_name', '')),
            'fingerprint' => trim((string) $this->input('fingerprint', '')),
            'support_until' => trim((string) $this->input('support_until', '')) ?: null,
            'grace_days' => trim((string) $this->input('grace_days', '')) ?: null,
            'allowed_hosts_text' => trim((string) ($this->input('allowed_hosts_text', $this->input('domains_text', '')))),
            'additional_limits' => trim((string) $this->input('additional_limits', '')) ?: null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        $hosts = preg_split('/\r\n|\r|\n/', (string) $this->validated('allowed_hosts_text', '')) ?: [];

        return array_values(array_filter(array_map(
            fn (string $host): string => trim($host),
            $hosts
        )));
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->allowedHosts();
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        $modules = $this->validated('modules', []);
        $preset = $this->preset();

        if (! is_array($modules) || $modules === []) {
            return $preset['modules'] ?? [];
        }

        return array_values($modules);
    }

    /**
     * @return array<string, int|string>
     */
    public function limits(): array
    {
        $preset = $this->preset();
        $limits = is_array($preset['limits'] ?? null) ? $preset['limits'] : [];

        foreach (['max_mikrotik', 'max_ppp_users'] as $key) {
            $value = $this->validated($key);

            if ($value !== null && $value !== '') {
                $limits[$key] = (int) $value;
            }
        }

        $additionalLimits = $this->validated('additional_limits');

        if (is_string($additionalLimits) && $additionalLimits !== '') {
            $decodedLimits = json_decode($additionalLimits, true);

            if (is_array($decodedLimits)) {
                foreach ($decodedLimits as $key => $value) {
                    if (! is_string($key) || $key === '' || is_array($value) || is_object($value) || $value === null) {
                        continue;
                    }

                    $limits[$key] = $value;
                }
            }
        }

        return $limits;
    }

    public function graceDays(): ?int
    {
        $graceDays = $this->validated('grace_days');

        if ($graceDays !== null && $graceDays !== '') {
            return (int) $graceDays;
        }

        $preset = $this->preset();

        return isset($preset['grace_days']) ? (int) $preset['grace_days'] : null;
    }

    public function accessMode(): ?string
    {
        $accessMode = $this->validated('access_mode');

        return is_string($accessMode) && $accessMode !== '' ? $accessMode : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function preset(): ?array
    {
        $presetKey = $this->validated('license_preset');

        if (! is_string($presetKey) || $presetKey === '') {
            return null;
        }

        return app(LicenseIssuerService::class)->presets()[$presetKey] ?? null;
    }
}
