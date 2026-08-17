<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\CompanyPermissionService;
use App\Services\ProductSellingPricePolicy;
use App\Models\Product;

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
        $existingProduct = $product instanceof Product ? $product : Product::query()
            ->where('business_id', $this->user()?->business_id)
            ->find($productId);
        $permissions = app(CompanyPermissionService::class);
        $canUseCategories = $permissions->allowsUser($this->user(), 'categories.view');
        $canUseUnits = $permissions->allowsUser($this->user(), 'units.view');

        return [
            'product_name' => ['required_without:name', 'string', 'max:255'], 'name' => ['required_without:product_name', 'string', 'max:255'],
            'category' => ['nullable', 'max:255'],
            'category_id' => $canUseCategories ? [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->user()->business_id)
                    ->where('type', 'Product')
                    ->whereNull('deleted_at')
                    ->where(fn ($status) => $status->where('status', 'Active')->orWhere('id', $existingProduct?->category_id))),
            ] : ['prohibited'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'batch_number' => ['nullable', 'string', 'max:100'], 'manufacturing_date' => ['nullable', 'date', 'before_or_equal:expiry_date'], 'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacturing_date'], 'expiry_alert_days' => ['nullable', 'integer', 'min:0'],
            'unit_id' => $canUseUnits ? [
                'required',
                Rule::exists('units', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->user()->business_id)
                    ->whereNull('deleted_at')
                    ->where(fn ($status) => $status->where('status', 'Active')->orWhere('id', $existingProduct?->unit_id))),
            ] : ['prohibited'],
            'status' => ['required', 'in:Active,Inactive'],
            // Keep product prices compatible with the existing whole-rupee
            // POS/payment calculation convention.
            'retail_price' => ['nullable', 'integer', 'min:0'],
            'wholesale_price' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'], 'brand' => ['nullable', 'string', 'max:100'], 'manufacturer' => ['nullable', 'string', 'max:100'], 'warehouse_location' => ['nullable', 'string', 'max:150'], 'has_batch_tracking' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $product = $this->route('product');
            if (! $product instanceof Product && $product !== null) {
                $product = Product::query()
                    ->where('business_id', $this->user()?->business_id)
                    ->find($product);
            }

            if (! $product instanceof Product || $product->business_id !== $this->user()?->business_id) {
                return;
            }

            $pricing = app(ProductSellingPricePolicy::class);

            foreach ($pricing->violations($this->only(['retail_price', 'wholesale_price']), $pricing->purchasePrice($product)) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }

}
