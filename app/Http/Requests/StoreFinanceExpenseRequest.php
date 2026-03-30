<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->isSuperAdmin() || in_array($user->role, ['administrator', 'keuangan'], true));
    }

    public function rules(): array
    {
        return [
            'owner_id' => [
                Rule::requiredIf(fn (): bool => (bool) $this->user()?->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:120'],
            'service_type' => ['required', 'string', 'in:general,pppoe,hotspot,voucher'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
