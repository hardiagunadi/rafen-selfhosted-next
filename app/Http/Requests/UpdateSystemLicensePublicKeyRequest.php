<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemLicensePublicKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        return [
            'license_public_key' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $decodedValue = base64_decode($value, true);
                    $expectedLength = defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES') ? SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES : 32;

                    if ($decodedValue === false || strlen($decodedValue) !== $expectedLength) {
                        $fail('Public key lisensi harus berupa string base64 Ed25519 yang valid.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'license_public_key.required' => 'Public key lisensi wajib diisi.',
            'license_public_key.string' => 'Public key lisensi harus berupa teks.',
            'license_public_key.max' => 'Public key lisensi terlalu panjang.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'license_public_key' => trim((string) $this->input('license_public_key', '')),
        ]);
    }
}
