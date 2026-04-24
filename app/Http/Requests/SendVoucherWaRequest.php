<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendVoucherWaRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'provider' => ['nullable', 'string', 'in:local,ycloud'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Nomor WhatsApp tujuan wajib diisi.',
            'phone.max' => 'Nomor WhatsApp tujuan terlalu panjang.',
            'phone.regex' => 'Format nomor WhatsApp hanya boleh berisi angka dan simbol telepon umum.',
            'provider.in' => 'Provider WhatsApp harus berupa gateway lokal atau YCloud.',
        ];
    }
}
