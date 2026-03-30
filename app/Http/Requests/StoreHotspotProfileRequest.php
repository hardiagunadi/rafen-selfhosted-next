<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotspotProfileRequest extends FormRequest
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
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'harga_promo' => ['required', 'numeric', 'min:0'],
            'ppn' => ['required', 'numeric', 'min:0'],
            'bandwidth_profile_id' => ['nullable', 'integer', 'exists:bandwidth_profiles,id'],
            'profile_type' => ['required', 'string', 'in:unlimited,limited'],
            'limit_type' => ['nullable', 'required_if:profile_type,limited', 'string', 'in:time,quota'],
            'time_limit_value' => ['nullable', 'required_if:limit_type,time', 'integer', 'min:1'],
            'time_limit_unit' => ['nullable', 'required_if:limit_type,time', 'string', 'in:menit,jam,hari,bulan'],
            'quota_limit_value' => ['nullable', 'required_if:limit_type,quota', 'numeric', 'min:1'],
            'quota_limit_unit' => ['nullable', 'required_if:limit_type,quota', 'string', 'in:mb,gb'],
            'masa_aktif_value' => ['nullable', 'required_if:profile_type,unlimited', 'integer', 'min:1'],
            'masa_aktif_unit' => ['nullable', 'required_if:profile_type,unlimited', 'string', 'in:menit,jam,hari,bulan'],
            'profile_group_id' => ['nullable', 'integer', 'exists:profile_groups,id'],
            'parent_queue' => ['nullable', 'string', 'max:200'],
            'shared_users' => ['required', 'integer', 'min:1'],
            'prioritas' => ['required', 'string', 'in:default,prioritas1,prioritas2,prioritas3,prioritas4,prioritas5,prioritas6,prioritas7,prioritas8'],
        ];
    }
}
