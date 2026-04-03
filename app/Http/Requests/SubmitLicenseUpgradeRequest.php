<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitLicenseUpgradeRequest extends FormRequest
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
            'delivery_mode' => ['nullable', 'string', Rule::in(['submit', 'download'])],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(['core', 'mikrotik', 'radius', 'vpn', 'wa', 'olt', 'genieacs'])],
            'max_mikrotik' => ['nullable', 'integer', 'min:-1'],
            'max_ppp_users' => ['nullable', 'integer', 'min:-1'],
            'max_vpn_peers' => ['nullable', 'integer', 'min:-1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_mode.in' => 'Mode pengiriman upgrade request tidak dikenali.',
            'modules.array' => 'Daftar modul upgrade tidak valid.',
            'modules.*.in' => 'Ada modul upgrade yang tidak dikenali.',
            'max_mikrotik.integer' => 'Limit Max Mikrotik harus berupa angka.',
            'max_mikrotik.min' => 'Limit Max Mikrotik minimal -1.',
            'max_ppp_users.integer' => 'Limit Max PPP Users harus berupa angka.',
            'max_ppp_users.min' => 'Limit Max PPP Users minimal -1.',
            'max_vpn_peers.integer' => 'Limit Max VPN Peers harus berupa angka.',
            'max_vpn_peers.min' => 'Limit Max VPN Peers minimal -1.',
            'notes.max' => 'Catatan ke vendor maksimal 2000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_mode' => trim((string) $this->input('delivery_mode', 'submit')) ?: 'submit',
            'notes' => trim((string) $this->input('notes', '')) ?: null,
            'max_mikrotik' => $this->normalizeIntegerInput('max_mikrotik'),
            'max_ppp_users' => $this->normalizeIntegerInput('max_ppp_users'),
            'max_vpn_peers' => $this->normalizeIntegerInput('max_vpn_peers'),
        ]);
    }

    public function shouldDownload(): bool
    {
        return $this->validated('delivery_mode', 'submit') === 'download';
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        $modules = $this->validated('modules', []);

        return is_array($modules) ? array_values($modules) : [];
    }

    /**
     * @return array<string, int>
     */
    public function limits(): array
    {
        return [
            'max_mikrotik' => (int) $this->validated('max_mikrotik', 0),
            'max_ppp_users' => (int) $this->validated('max_ppp_users', 0),
            'max_vpn_peers' => (int) $this->validated('max_vpn_peers', 0),
        ];
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) && $notes !== '' ? $notes : null;
    }

    private function normalizeIntegerInput(string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return 0;
        }

        return $value;
    }
}
