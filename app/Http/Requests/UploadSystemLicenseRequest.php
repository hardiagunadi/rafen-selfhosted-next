<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSystemLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'license_file' => ['required', 'file', 'max:256', 'extensions:json,txt,lic'],
        ];
    }

    public function messages(): array
    {
        return [
            'license_file.required' => 'File lisensi wajib dipilih.',
            'license_file.file' => 'File lisensi tidak valid.',
            'license_file.max' => 'Ukuran file lisensi maksimal 256 KB.',
            'license_file.extensions' => 'Format file lisensi harus json, txt, atau lic.',
        ];
    }
}
