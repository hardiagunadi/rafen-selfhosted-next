<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWgPeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $peerId = $this->route('wgPeer')?->id;

        return [
            'name'          => ['required', 'string', 'max:150'],
            'public_key'    => ['required', 'string', 'max:255', 'unique:wg_peers,public_key,' . $peerId],
            'private_key'   => ['required', 'string', 'max:255'],
            'preshared_key' => ['nullable', 'string', 'max:255'],
            'vpn_ip'        => ['nullable', 'ip', 'unique:wg_peers,vpn_ip,' . $peerId],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Nama peer wajib diisi.',
            'public_key.required'  => 'Public key wajib diisi.',
            'public_key.unique'    => 'Public key sudah digunakan.',
            'private_key.required' => 'Private key wajib diisi.',
            'vpn_ip.unique'        => 'IP VPN sudah digunakan.',
            'vpn_ip.ip'            => 'Format IP VPN tidak valid.',
        ];
    }
}
