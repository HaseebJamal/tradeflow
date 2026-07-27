<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('business_id', $this->user()->business_id)->where('type', 'Product'))
                    ->ignore(is_object($category) ? $category->id : $category),
            ],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This category already exists for this business.',
        ];
    }
}
