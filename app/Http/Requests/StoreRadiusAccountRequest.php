<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRadiusAccountRequest extends FormRequest
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
            'mikrotik_connection_id' => ['nullable', 'integer', 'exists:mikrotik_connections,id'],
            'username' => ['required', 'string', 'max:120', 'unique:radius_accounts,username'],
            'password' => ['required', 'string', 'max:191'],
            'service' => ['required', 'string', 'in:pppoe,hotspot'],
            'ipv4_address' => ['required_if:service,pppoe', 'nullable', 'ipv4'],
            'rate_limit' => ['nullable', 'string', 'max:120'],
            'profile' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
