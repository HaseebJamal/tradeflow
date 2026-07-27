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
            'category' => ['nullable', 'max:255'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('business_id', $this->user()->business_id)->where('type', 'Product')->whereNull('deleted_at')),
            ],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'batch_number' => ['nullable', 'max:100'], 'manufacturing_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date'], 'expiry_alert_days' => ['nullable', 'integer', 'min:0'],
            'unit_id' => [
                'required',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('business_id', $this->user()->business_id)->whereNull('deleted_at')),
            ],
            'status' => ['required', 'in:Active,Inactive'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'], 'brand' => ['nullable', 'max:100'], 'manufacturer' => ['nullable', 'max:100'], 'warehouse_location' => ['nullable', 'max:150'], 'has_batch_tracking' => ['nullable', 'boolean'],
        ];
    }

}
