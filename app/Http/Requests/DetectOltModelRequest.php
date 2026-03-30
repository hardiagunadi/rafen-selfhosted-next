<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DetectOltModelRequest extends FormRequest
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
            'vendor' => ['required', 'in:hsgq'],
            'host' => ['required', 'string', 'max:191'],
            'snmp_port' => ['required', 'integer', 'between:1,65535'],
            'snmp_version' => ['required', 'in:2c'],
            'snmp_community' => ['required', 'string', 'max:191'],
            'snmp_timeout' => ['required', 'integer', 'between:1,30'],
            'snmp_retries' => ['required', 'integer', 'between:0,5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.required' => 'Host / IP OLT wajib diisi sebelum deteksi model.',
            'snmp_port.required' => 'Port SNMP wajib diisi sebelum deteksi model.',
            'snmp_version.required' => 'Versi SNMP wajib dipilih sebelum deteksi model.',
            'snmp_community.required' => 'SNMP Community wajib diisi sebelum deteksi model.',
            'snmp_timeout.required' => 'SNMP Timeout wajib diisi sebelum deteksi model.',
            'snmp_retries.required' => 'SNMP Retries wajib diisi sebelum deteksi model.',
        ];
    }
}
