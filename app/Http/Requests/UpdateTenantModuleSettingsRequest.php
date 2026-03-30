<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->isSubUser();
    }

    public function rules(): array
    {
        return [
            'module_hotspot_enabled' => ['required', 'boolean'],
            'shift_feature_enabled' => ['required', 'boolean'],
            'wa_shift_group_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'module_hotspot_enabled' => $this->boolean('module_hotspot_enabled'),
            'shift_feature_enabled' => $this->boolean('shift_feature_enabled'),
        ]);
    }
}
