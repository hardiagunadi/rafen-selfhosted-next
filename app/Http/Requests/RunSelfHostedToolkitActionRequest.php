<?php

namespace App\Http\Requests;

use App\Services\SelfHostedToolkitService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunSelfHostedToolkitActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(SelfHostedToolkitService::actionKeys())],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi toolkit wajib dipilih.',
            'action.in' => 'Aksi toolkit self-hosted tidak valid.',
        ];
    }
}
