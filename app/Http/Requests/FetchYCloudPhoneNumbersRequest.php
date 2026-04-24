<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchYCloudPhoneNumbersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer'],
            'ycloud_api_key' => ['nullable', 'string', 'max:10000'],
            'ycloud_waba_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.integer' => 'Tenant tidak valid.',
            'ycloud_api_key.max' => 'API key YCloud terlalu panjang.',
            'ycloud_waba_id.max' => 'WABA ID maksimal 100 karakter.',
        ];
    }
}
