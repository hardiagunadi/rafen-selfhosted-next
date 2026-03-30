<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => array_filter(['required', 'string', 'min:8', $this->routeIs('register', 'register.submit') ? 'confirmed' : null]),
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'required_if:role,teknisi'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'required', 'string', 'in:administrator,it_support,noc,keuangan,teknisi,cs'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];

        if ($this->routeIs('register', 'register.submit')) {
            $rules['admin_subdomain'] = [
                'required', 'string', 'max:63', 'regex:/^[a-z0-9\-]+$/',
                'unique:tenant_settings,admin_subdomain',
                'unique:tenant_settings,portal_slug',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama tidak boleh lebih dari 150 karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar sebagai akun. Silakan login atau gunakan email lain.',
            'email.max' => 'Email tidak boleh lebih dari 191 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'phone.required_if' => 'Nomor HP / WhatsApp wajib diisi untuk pengguna dengan role teknisi.',
            'phone.max' => 'Nomor HP / WhatsApp tidak boleh lebih dari 20 karakter.',
            'role.in' => 'Role yang dipilih tidak valid.',
            'admin_subdomain.required' => 'Subdomain wajib diisi.',
            'admin_subdomain.regex' => 'Subdomain hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'admin_subdomain.unique' => 'Subdomain ini sudah digunakan. Coba yang lain.',
        ];
    }
}
