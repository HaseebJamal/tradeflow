<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_type' => ['required', Rule::in(['Cash', 'Credit', 'Split', 'Bank Transfer', 'JazzCash Manual', 'Easypaisa Manual', 'Cheque'])],
            'payment_method' => ['required', 'string', 'max:100'],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:255'],
            'quick_customer' => ['nullable', 'array'],
            'quick_customer.name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'quick_customer.phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'quick_customer.city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'quick_customer.address' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
