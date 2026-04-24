<?php

namespace App\Http\Requests;

use App\Models\PppUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $pppUser = $this->route('pppUser');

        return $user !== null
            && $pppUser instanceof PppUser
            && ($user->isSuperAdmin() || $pppUser->owner_id === $user->effectiveOwnerId())
            && (! $user->isTeknisi() || $pppUser->assigned_teknisi_id === null || $pppUser->assigned_teknisi_id === $user->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note_type' => ['required', 'string', 'in:aktivasi,pemasangan,perbaikan,lainnya'],
            'document_title' => ['required', 'string', 'max:150'],
            'summary_title' => ['required', 'string', 'max:150'],
            'document_number' => ['required', 'string', 'max:120'],
            'note_date' => ['required', 'date'],
            'service_type' => ['required', 'string', 'in:general,pppoe,hotspot'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,lainnya'],
            'show_service_section' => ['required', 'boolean'],
            'item_lines' => ['required', 'array', 'min:1', 'max:10'],
            'item_lines.*.label' => ['required', 'string', 'max:120'],
            'item_lines.*.amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'footer' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $itemLines = $this->input('item_lines');

        if (is_string($itemLines)) {
            $decoded = json_decode($itemLines, true);

            if (is_array($decoded)) {
                $this->merge([
                    'item_lines' => $decoded,
                ]);
            }
        }

        $this->merge([
            'show_service_section' => $this->has('show_service_section')
                ? $this->boolean('show_service_section')
                : true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_lines.required' => 'Minimal 1 item biaya harus diisi.',
            'item_lines.*.label.required' => 'Nama item biaya wajib diisi.',
            'item_lines.*.amount.required' => 'Nominal item biaya wajib diisi.',
        ];
    }
}
