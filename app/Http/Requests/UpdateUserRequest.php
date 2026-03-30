<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'required_if:role,teknisi'],
            'role' => ['sometimes', 'required', 'string', 'in:administrator,it_support,noc,keuangan,teknisi,cs'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_if' => 'Nomor HP / WhatsApp wajib diisi untuk pengguna dengan role teknisi.',
            'phone.max' => 'Nomor HP / WhatsApp tidak boleh lebih dari 20 karakter.',
        ];
    }
}
