<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBandwidthProfileRequest extends FormRequest
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
            'upload_min_mbps' => ['required', 'integer', 'min:0'],
            'upload_max_mbps' => ['required', 'integer', 'min:0'],
            'download_min_mbps' => ['required', 'integer', 'min:0'],
            'download_max_mbps' => ['required', 'integer', 'min:0'],
            'owner' => ['nullable', 'string', 'max:120'],
        ];
    }
}
