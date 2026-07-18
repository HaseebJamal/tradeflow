<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return [
            'unit_name' => ['required', 'string', 'max:100'],
            'unit_type' => ['required', Rule::in(['Piece', 'Weight', 'Volume', 'Length', 'Area', 'Pack', 'Other'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
