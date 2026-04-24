<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestYCloudWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:20'],
            'ycloud_api_key' => ['nullable', 'string', 'max:10000'],
            'ycloud_phone_number_id' => ['nullable', 'string', 'max:100'],
            'ycloud_waba_id' => ['nullable', 'string', 'max:100'],
            'ycloud_business_number' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.max' => 'Nomor tujuan maksimal 20 karakter.',
            'tenant_id.integer' => 'Tenant tidak valid.',
            'ycloud_api_key.max' => 'API key YCloud terlalu panjang.',
            'ycloud_phone_number_id.max' => 'Phone Number ID maksimal 100 karakter.',
            'ycloud_waba_id.max' => 'WABA ID maksimal 100 karakter.',
            'ycloud_business_number.max' => 'Nomor bisnis YCloud maksimal 30 karakter.',
        ];
    }
}
