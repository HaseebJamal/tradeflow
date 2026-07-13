<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'product_name' => ['required_without:name', 'max:255'], 'name' => ['required_without:product_name', 'max:255'],
            'category' => ['required_without:category_id', 'max:255'], 'category_id' => ['nullable', 'exists:categories,id'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sku' => ['nullable', 'max:100'],
            'barcode' => ['nullable', 'max:100', Rule::unique('products', 'barcode')->where('business_id', $this->user()->business_id)->whereNull('deleted_at')->ignore($productId)],
            'batch_number' => ['nullable', 'max:100'], 'manufacturing_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date'], 'expiry_alert_days' => ['nullable', 'integer', 'min:0'],
            'purchase_cost' => ['required', 'numeric', 'min:0'], 'wholesale_price' => ['required', 'numeric', 'min:0'], 'retail_price' => ['required', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'low_stock_alert_qty' => ['nullable', 'integer', 'min:0'], 'unit' => ['required', 'in:Piece,Carton,KG,Liter'], 'status' => ['required', 'in:Active,Inactive'],
            'description' => ['nullable', 'string'], 'brand' => ['nullable', 'max:100'], 'manufacturer' => ['nullable', 'max:100'], 'warehouse_location' => ['nullable', 'max:150'], 'has_batch_tracking' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $purchase = $this->input('purchase_cost');
            if (!is_numeric($purchase)) return;

            if (is_numeric($this->input('wholesale_price')) && (float) $this->input('wholesale_price') <= (float) $purchase) {
                $validator->errors()->add('wholesale_price', 'Selling Price must be greater than Purchase Price.');
            }
            if (is_numeric($this->input('retail_price')) && (float) $this->input('retail_price') > 0 && (float) $this->input('retail_price') <= (float) $purchase) {
                $validator->errors()->add('retail_price', 'Selling Price must be greater than Purchase Price.');
            }
        });
    }
}
