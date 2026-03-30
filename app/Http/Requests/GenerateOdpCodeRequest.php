<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateOdpCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'location_code' => ['required', 'string', 'max:80'],
            'area_name' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.required' => 'Owner data wajib dipilih.',
            'owner_id.exists' => 'Owner data tidak valid.',
            'location_code.required' => 'Kode lokasi wajib tersedia.',
            'area_name.required' => 'Nama wilayah wajib tersedia.',
        ];
    }
}
