<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RebootOltOnuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'onu_index' => ['required', 'regex:/^[0-9]+(?:\.[0-9]+)*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'onu_index.required' => 'ONU index wajib diisi.',
            'onu_index.regex' => 'Format ONU index tidak valid.',
        ];
    }
}
