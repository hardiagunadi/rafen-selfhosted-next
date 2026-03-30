<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCpePppoeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username PPPoE wajib diisi.',
            'username.max'      => 'Username PPPoE maksimal 64 karakter.',
            'password.required' => 'Password PPPoE wajib diisi.',
            'password.max'      => 'Password PPPoE maksimal 64 karakter.',
        ];
    }
}
