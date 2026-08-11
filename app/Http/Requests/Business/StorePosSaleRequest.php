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

    protected function prepareForValidation(): void
    {
        $normalizeMoney = static function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }

            return str_replace(',', '', preg_replace('/^\s*Rs\.?\s*/i', '', trim($value)) ?? '');
        };

        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach (['unit_price', 'discount_rate', 'tax_rate'] as $field) {
                    if (array_key_exists($field, $item)) {
                        $items[$index][$field] = $normalizeMoney($item[$field]);
                    }
                }
            }
        }

        $this->merge([
            'cash_received' => $normalizeMoney($this->input('cash_received')),
            'discount' => $normalizeMoney($this->input('discount')),
            'tax_rate' => $normalizeMoney($this->input('tax_rate')),
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_type' => ['required', Rule::in(['Cash', 'Credit', 'Split', 'Bank Transfer', 'Jazz Cash', 'Easypaisa', 'Cheque'])],
            'payment_method' => ['required', Rule::in(['Cash', 'Credit', 'Split', 'Bank Transfer', 'Jazz Cash', 'Easypaisa', 'Cheque'])],
            'cash_received' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'quick_customer' => ['nullable', 'array'],
            'quick_customer.name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'quick_customer.phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'quick_customer.city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'quick_customer.address' => ['nullable', 'string', 'max:500'],
            'delivery_required' => ['nullable', 'boolean'],
            'delivery_address' => [Rule::requiredIf(fn () => $this->boolean('delivery_required')), 'nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
