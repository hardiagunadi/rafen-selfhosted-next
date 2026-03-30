<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePppProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'harga_modal' => ['required', 'numeric', 'min:0'],
            'harga_promo' => ['required', 'numeric', 'min:0'],
            'ppn' => ['required', 'numeric', 'min:0'],
            'profile_group_id' => ['nullable', 'integer', 'exists:profile_groups,id'],
            'bandwidth_profile_id' => ['nullable', 'integer', 'exists:bandwidth_profiles,id'],
            'parent_queue' => ['nullable', 'string', 'max:200'],
            'masa_aktif' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'in:bulan'],
        ];
    }
}
