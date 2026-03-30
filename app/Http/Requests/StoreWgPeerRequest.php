<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWgPeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mikrotik_connection_id' => ['nullable', 'integer', 'exists:mikrotik_connections,id', 'unique:wg_peers,mikrotik_connection_id'],
            'name'                   => ['required', 'string', 'max:150'],
            'public_key'             => ['nullable', 'string', 'max:255', 'unique:wg_peers,public_key'],
            'private_key'            => ['nullable', 'string', 'max:255'],
            'preshared_key'          => ['nullable', 'string', 'max:255'],
            'vpn_ip'                 => ['nullable', 'ip', 'unique:wg_peers,vpn_ip'],
            'is_active'              => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'mikrotik_connection_id.exists'  => 'Router tidak ditemukan.',
            'mikrotik_connection_id.unique'  => 'Router sudah memiliki WireGuard peer.',
            'name.required'                  => 'Nama peer wajib diisi.',
            'public_key.unique'              => 'Public key sudah digunakan.',
            'vpn_ip.unique'                  => 'IP VPN sudah digunakan.',
            'vpn_ip.ip'                      => 'Format IP VPN tidak valid.',
        ];
    }
}
