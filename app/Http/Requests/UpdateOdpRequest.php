<?php

namespace App\Http\Requests;

use App\Models\Odp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOdpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Odp|null $odp */
        $odp = $this->route('odp');
        $ownerId = (int) $this->input('owner_id');

        return [
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('odps', 'code')
                    ->ignore($odp?->id)
                    ->where(fn ($query) => $query->where('owner_id', $ownerId)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'area' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity_ports' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', 'string', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.required' => 'Owner data wajib dipilih.',
            'owner_id.exists' => 'Owner data tidak valid.',
            'code.required' => 'Kode ODP wajib diisi.',
            'code.unique' => 'Kode ODP sudah dipakai oleh owner ini.',
            'name.required' => 'Nama ODP wajib diisi.',
            'latitude.between' => 'Latitude harus berada di antara -90 sampai 90.',
            'longitude.between' => 'Longitude harus berada di antara -180 sampai 180.',
            'capacity_ports.integer' => 'Kapasitas port harus berupa angka bulat.',
            'status.in' => 'Status ODP tidak valid.',
        ];
    }
}
