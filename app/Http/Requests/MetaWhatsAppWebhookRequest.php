<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MetaWhatsAppWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'object' => ['required', 'string', 'in:whatsapp_business_account'],
            'entry' => ['required', 'array'],
            'entry.*' => ['array'],
            'entry.*.changes' => ['nullable', 'array'],
        ];
    }
}
