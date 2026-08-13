<?php

namespace App\Http\Requests\Business;

use App\Services\ProductSellingPricePolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkStoreProductRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->business_id !== null; }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'], 'products.*.name' => ['required', 'max:255'], 'products.*.category' => ['required', 'max:255'], 'products.*.unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'products.*.purchase_cost' => ['required', 'integer', 'min:0'], 'products.*.wholesale_price' => ['required', 'integer', 'min:0'], 'products.*.retail_price' => ['nullable', 'integer', 'min:0'],
            'products.*.batch_number' => ['nullable', 'max:100'], 'products.*.has_batch_tracking' => ['nullable', 'boolean'], 'products.*.expiry_date' => ['nullable', 'date'], 'products.*.low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('products', []) as $index => $product) {
                if (! is_numeric($product['purchase_cost'] ?? null)) {
                    continue;
                }

                foreach (app(ProductSellingPricePolicy::class)->violations($product, (float) $product['purchase_cost']) as $field => $message) {
                    $validator->errors()->add("products.{$index}.{$field}", $message);
                }
            }
        });
    }
}
