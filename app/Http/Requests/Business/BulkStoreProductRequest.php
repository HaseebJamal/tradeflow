<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreProductRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->business_id !== null; }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'], 'products.*.name' => ['required', 'max:255'], 'products.*.category' => ['required', 'max:255'], 'products.*.unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'products.*.purchase_cost' => ['required', 'numeric', 'min:0'], 'products.*.wholesale_price' => ['required', 'numeric', 'min:0'], 'products.*.retail_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.sku' => ['nullable', 'max:100'], 'products.*.barcode' => ['nullable', 'max:100'], 'products.*.batch_number' => ['nullable', 'max:100'], 'products.*.expiry_date' => ['nullable', 'date'], 'products.*.low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('products', []) as $index => $product) {
                if (!is_numeric($product['purchase_cost'] ?? null)) continue;
                if (is_numeric($product['wholesale_price'] ?? null) && (float) $product['wholesale_price'] <= (float) $product['purchase_cost']) {
                    $validator->errors()->add("products.$index.wholesale_price", 'Selling Price must be greater than Purchase Price.');
                }
                if (is_numeric($product['retail_price'] ?? null) && (float) $product['retail_price'] > 0 && (float) $product['retail_price'] <= (float) $product['purchase_cost']) {
                    $validator->errors()->add("products.$index.retail_price", 'Selling Price must be greater than Purchase Price.');
                }
            }
        });
    }
}
