<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotspotUserRequest extends FormRequest
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
            'status_registrasi'  => ['required', 'string', 'in:aktif,on_process'],
            'tipe_pembayaran'    => ['required', 'string', 'in:prepaid,postpaid'],
            'status_bayar'       => ['required', 'string', 'in:sudah_bayar,belum_bayar'],
            'status_akun'        => ['required', 'string', 'in:enable,disable,isolir'],
            'owner_id'           => ['required', 'integer', 'exists:users,id'],
            'hotspot_profile_id' => ['required', 'integer', 'exists:hotspot_profiles,id'],
            'profile_group_id'   => ['nullable', 'integer', 'exists:profile_groups,id'],
            'tagihkan_ppn'       => ['sometimes', 'boolean'],
            'biaya_instalasi'    => ['nullable', 'numeric', 'min:0'],
            'jatuh_tempo'        => ['nullable', 'date'],
            'aksi_jatuh_tempo'   => ['required', 'string', 'in:isolir,tetap_terhubung'],
            'customer_id'        => ['nullable', 'string', 'max:120'],
            'customer_name'      => ['required', 'string', 'max:150'],
            'nik'                => ['nullable', 'string', 'max:50'],
            'nomor_hp'           => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:191'],
            'alamat'             => ['nullable', 'string'],
            'metode_login'       => ['required', 'string', 'in:username_password,username_equals_password'],
            'username'           => ['required', 'string', 'max:120', 'unique:hotspot_users,username'],
            'hotspot_password'   => ['nullable', 'string', 'max:120', 'required_if:metode_login,username_password'],
            'catatan'            => ['nullable', 'string'],
        ];
    }
}
