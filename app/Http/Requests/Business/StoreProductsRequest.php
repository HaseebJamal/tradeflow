<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'products' => ['required', 'array', 'min:1', 'max:25'],
            'products.*.product_name' => ['required', 'string', 'max:255'],
            'products.*.category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->where('type', 'Product')
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')),
            ],
            'products.*.unit_id' => [
                'required',
                Rule::exists('units', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')),
            ],
            'products.*.product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'products.*.brand' => ['nullable', 'string', 'max:100'],
            'products.*.manufacturer' => ['nullable', 'string', 'max:100'],
            'products.*.warehouse_location' => ['nullable', 'string', 'max:150'],
            'products.*.has_batch_tracking' => ['nullable', 'boolean'],
            'products.*.batch_number' => ['nullable', 'string', 'max:100'],
            'products.*.manufacturing_date' => ['nullable', 'date'],
            'products.*.expiry_date' => ['nullable', 'date'],
            'products.*.expiry_alert_days' => ['nullable', 'integer', 'min:0'],
            'products.*.status' => ['required', Rule::in(['Active', 'Inactive'])],
            'products.*.description' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $names = [];

            foreach ($this->input('products', []) as $index => $product) {
                $name = mb_strtolower(trim((string) ($product['product_name'] ?? '')));
                if ($name === '') {
                    continue;
                }

                if (isset($names[$name])) {
                    $validator->errors()->add("products.{$index}.product_name", 'Duplicate product names cannot be saved in the same submission.');
                    $validator->errors()->add("products.{$names[$name]}.product_name", 'Duplicate product names cannot be saved in the same submission.');
                    continue;
                }

                $names[$name] = $index;
            }
        });
    }
}
