<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestMikrotikConnectionRequest extends FormRequest
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
            'host' => ['required', 'string', 'max:191'],
            'api_timeout' => ['nullable', 'integer', 'between:1,120'],
            'api_port' => ['nullable', 'integer', 'between:1,65535'],
            'api_ssl_port' => ['nullable', 'integer', 'between:1,65535'],
            'use_ssl' => ['nullable', 'boolean'],
        ];
    }
}
