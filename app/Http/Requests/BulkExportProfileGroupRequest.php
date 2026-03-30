<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkExportProfileGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_group_ids' => ['required', 'array', 'min:1'],
            'profile_group_ids.*' => ['integer', 'exists:profile_groups,id'],
            'mikrotik_connection_ids' => ['required', 'array', 'min:1'],
            'mikrotik_connection_ids.*' => ['integer', 'exists:mikrotik_connections,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_group_ids.required' => 'Pilih minimal satu profil group.',
            'profile_group_ids.array' => 'Data profil group tidak valid.',
            'profile_group_ids.min' => 'Pilih minimal satu profil group.',
            'profile_group_ids.*.integer' => 'Profil group tidak valid.',
            'profile_group_ids.*.exists' => 'Profil group tidak ditemukan.',
            'mikrotik_connection_ids.required' => 'Pilih minimal satu router (NAS) untuk export.',
            'mikrotik_connection_ids.array' => 'Data router (NAS) tidak valid.',
            'mikrotik_connection_ids.min' => 'Pilih minimal satu router (NAS) untuk export.',
            'mikrotik_connection_ids.*.integer' => 'Router (NAS) tidak valid.',
            'mikrotik_connection_ids.*.exists' => 'Router (NAS) tidak ditemukan.',
        ];
    }
}
