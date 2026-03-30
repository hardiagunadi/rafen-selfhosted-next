<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestMetaWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:20'],
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
        ];
    }
}
