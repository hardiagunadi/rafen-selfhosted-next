<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOltConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role !== 'teknisi';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor' => ['sometimes', 'required', 'in:hsgq'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'olt_model' => ['sometimes', 'required', 'string', 'max:120'],
            'host' => ['sometimes', 'required', 'string', 'max:191'],
            'snmp_port' => ['sometimes', 'required', 'integer', 'between:1,65535'],
            'snmp_version' => ['sometimes', 'required', 'in:2c'],
            'snmp_community' => ['sometimes', 'required', 'string', 'max:191'],
            'snmp_write_community' => ['nullable', 'string', 'max:191'],
            'snmp_timeout' => ['sometimes', 'required', 'integer', 'between:1,30'],
            'snmp_retries' => ['sometimes', 'required', 'integer', 'between:0,5'],
            'is_active' => ['sometimes', 'boolean'],
            'oid_serial' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_onu_name' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_rx_onu' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_tx_onu' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_rx_olt' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_tx_olt' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_distance' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_status' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'oid_reboot_onu' => ['nullable', 'regex:/^[0-9]+(?:\\.[0-9]+)*$/'],
            'cli_protocol' => ['nullable', 'in:none,telnet,ssh'],
            'cli_port' => ['nullable', 'integer', 'between:1,65535'],
            'cli_username' => ['nullable', 'string', 'max:191'],
            'cli_password' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'olt_model.required' => 'Model OLT HSGQ wajib dipilih.',
            'oid_serial.regex' => 'OID MAC / identifier ONU harus berupa angka dengan pemisah titik, contoh: 1.3.6.1.4.1',
            'oid_onu_name.regex' => 'OID nama ONU harus berupa angka dengan pemisah titik.',
            'oid_rx_onu.regex' => 'OID Rx ONU harus berupa angka dengan pemisah titik.',
            'oid_tx_onu.regex' => 'OID Tx ONU harus berupa angka dengan pemisah titik.',
            'oid_rx_olt.regex' => 'OID Rx OLT harus berupa angka dengan pemisah titik.',
            'oid_tx_olt.regex' => 'OID Tx OLT harus berupa angka dengan pemisah titik.',
            'oid_distance.regex' => 'OID Distance harus berupa angka dengan pemisah titik.',
            'oid_status.regex' => 'OID status ONU harus berupa angka dengan pemisah titik.',
            'oid_reboot_onu.regex' => 'OID reboot ONU harus berupa angka dengan pemisah titik.',
        ];
    }
}
