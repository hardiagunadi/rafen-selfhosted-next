<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePppUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email.email' => 'Format :attribute tidak valid.',
            'ppp_password.required_if' => 'Password PPPoE/L2TP/OVPN wajib diisi jika metode login Username & Password.',
            'nomor_hp.unique' => 'Nomor HP ini sudah digunakan oleh pelanggan lain. Setiap pelanggan harus memiliki nomor HP yang unik agar bisa login ke portal.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status_registrasi' => 'Status Registrasi',
            'tipe_pembayaran' => 'Tipe Pembayaran',
            'status_bayar' => 'Status Bayar',
            'status_akun' => 'Status Akun',
            'owner_id' => 'Owner Data',
            'ppp_profile_id' => 'Paket Langganan',
            'tipe_service' => 'Tipe Service',
            'jatuh_tempo' => 'Ubah Jatuh Tempo',
            'aksi_jatuh_tempo' => 'Aksi Jatuh Tempo',
            'tipe_ip' => 'Tipe IP',
            'customer_id' => 'ID Pelanggan',
            'customer_name' => 'Nama',
            'nik' => 'No. NIK',
            'nomor_hp' => 'Nomor HP',
            'email' => 'Email',
            'alamat' => 'Alamat',
            'metode_login' => 'Metode Login',
            'username' => 'Username',
            'ppp_password' => 'Password PPPoE/L2TP/OVPN',
            'password_clientarea' => 'Password Clientarea',
            'assigned_teknisi_id' => 'Teknisi yang Ditugaskan',
        ];
    }

    public function rules(): array
    {
        return [
            'status_registrasi' => ['sometimes', 'required', 'string', 'in:aktif,on_process'],
            'tipe_pembayaran' => ['sometimes', 'required', 'string', 'in:prepaid,postpaid'],
            'status_bayar' => ['sometimes', 'required', 'string', 'in:sudah_bayar,belum_bayar'],
            'status_akun' => ['sometimes', 'required', 'string', 'in:enable,disable,isolir'],
            'owner_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'ppp_profile_id' => ['sometimes', 'required', 'integer', 'exists:ppp_profiles,id'],
            'tipe_service' => ['sometimes', 'required', 'string', 'in:pppoe,l2tp_pptp,openvpn_sstp'],
            'tagihkan_ppn' => ['sometimes', 'boolean'],
            'prorata_otomatis' => ['sometimes', 'boolean'],
            'promo_aktif' => ['sometimes', 'boolean'],
            'durasi_promo_bulan' => ['nullable', 'integer', 'min:0'],
            'biaya_instalasi' => ['nullable', 'numeric', 'min:0'],
            'jatuh_tempo' => ['nullable', 'date'],
            'aksi_jatuh_tempo' => ['sometimes', 'required', 'string', 'in:isolir,tetap_terhubung'],
            'tipe_ip' => ['sometimes', 'required', 'string', 'in:dhcp,static'],
            'profile_group_id' => ['nullable', 'integer', 'exists:profile_groups,id'],
            'ip_static' => ['nullable', 'string', 'max:120'],
            'odp_id' => ['sometimes', 'nullable', 'integer', 'exists:odps,id'],
            'odp_pop' => ['sometimes', 'nullable', 'string', 'max:120'],
            'customer_id' => ['sometimes', 'required', 'string', 'max:120'],
            'customer_name' => ['sometimes', 'required', 'string', 'max:150'],
            'nik' => ['sometimes', 'required', 'string', 'max:191'],
            'nomor_hp' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('ppp_users')->where('owner_id',
                    $this->route('ppp_user')?->owner_id ?? $this->user()?->effectiveOwnerId()
                )->ignore($this->route('ppp_user')?->id),
            ],
            'email' => ['sometimes', 'required', 'email', 'max:191'],
            'alamat' => ['sometimes', 'required', 'string'],
            'latitude' => ['nullable', 'string', 'max:120'],
            'longitude' => ['nullable', 'string', 'max:120'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'location_capture_method' => ['nullable', 'string', 'in:gps,map_picker,manual'],
            'location_captured_at' => ['nullable', 'date'],
            'metode_login' => ['sometimes', 'required', 'string', 'in:username_password,username_equals_password'],
            'username' => ['sometimes', 'required', 'string', 'max:120'],
            'ppp_password' => ['nullable', 'string', 'max:120', 'required_if:metode_login,username_password'],
            'password_clientarea' => ['nullable', 'string', 'max:120'],
            'catatan' => ['nullable', 'string'],
            'assigned_teknisi_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'teknisi')],
        ];
    }
}
