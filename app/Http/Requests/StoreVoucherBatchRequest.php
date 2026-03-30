<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hotspot_profile_id' => ['required', 'integer', 'exists:hotspot_profiles,id'],
            'batch_name'         => ['required', 'string', 'max:120'],
            'jumlah'             => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hotspot_profile_id.required' => 'Pilih profil hotspot terlebih dahulu.',
            'hotspot_profile_id.exists'   => 'Profil hotspot tidak ditemukan.',
            'batch_name.required'         => 'Nama batch wajib diisi.',
            'jumlah.required'             => 'Jumlah voucher wajib diisi.',
            'jumlah.min'                  => 'Jumlah voucher minimal 1.',
            'jumlah.max'                  => 'Jumlah voucher maksimal 1000 per batch.',
        ];
    }
}
