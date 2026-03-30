<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCpeWifiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ssid' => ['required', 'string', 'min:1', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:63'],
        ];
    }

    public function messages(): array
    {
        return [
            'ssid.required' => 'Nama WiFi (SSID) wajib diisi.',
            'ssid.max' => 'Nama WiFi maksimal 32 karakter.',
            'password.min' => 'Password WiFi minimal 8 karakter.',
            'password.max' => 'Password WiFi maksimal 63 karakter.',
        ];
    }
}
