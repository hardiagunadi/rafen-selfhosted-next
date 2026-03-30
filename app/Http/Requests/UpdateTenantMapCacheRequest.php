<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantMapCacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isSubUser();
    }

    public function rules(): array
    {
        return [
            'map_cache_enabled' => ['sometimes', 'boolean'],
            'map_cache_center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_cache_center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'map_cache_radius_km' => ['nullable', 'numeric', 'min:0.2', 'max:50'],
            'map_cache_min_zoom' => ['nullable', 'integer', 'min:10', 'max:18'],
            'map_cache_max_zoom' => ['nullable', 'integer', 'min:11', 'max:19'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $minZoom = (int) $this->input('map_cache_min_zoom', 14);
            $maxZoom = (int) $this->input('map_cache_max_zoom', 17);

            if ($maxZoom < $minZoom) {
                $validator->errors()->add('map_cache_max_zoom', 'Zoom maksimum tidak boleh lebih kecil dari zoom minimum.');
            }

            $enabled = filter_var($this->input('map_cache_enabled', false), FILTER_VALIDATE_BOOLEAN);
            $latitude = $this->input('map_cache_center_lat');
            $longitude = $this->input('map_cache_center_lng');

            if ($enabled && ($latitude === null || $longitude === null || $latitude === '' || $longitude === '')) {
                $validator->errors()->add('map_cache_center_lat', 'Titik pusat coverage wajib diisi saat cache peta diaktifkan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'map_cache_center_lat.between' => 'Latitude coverage harus di antara -90 sampai 90.',
            'map_cache_center_lng.between' => 'Longitude coverage harus di antara -180 sampai 180.',
            'map_cache_radius_km.min' => 'Radius coverage minimal 0.2 km.',
            'map_cache_radius_km.max' => 'Radius coverage maksimal 50 km.',
        ];
    }
}
