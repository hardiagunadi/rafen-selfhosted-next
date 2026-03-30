<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchOltOnuAlarmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
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
