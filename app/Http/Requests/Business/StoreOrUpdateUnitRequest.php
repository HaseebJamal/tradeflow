<?php

namespace App\Http\Requests\Business;

use App\Models\Unit;
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
        $unit = $this->route('unit');
        $unitId = $unit instanceof Unit ? $unit->getKey() : $unit;

        return [
            'unit_name' => [
                'required',
                'string',
                'max:100',
            ],
            'unit_name_normalized' => [
                Rule::unique('units', 'unit_name_normalized')
                    ->where(fn ($query) => $query->where('business_id', $this->user()->business_id))
                    ->ignore($unitId),
            ],
            'unit_type' => ['required', Rule::in(['Piece', 'Weight', 'Volume', 'Length', 'Area', 'Pack', 'Other'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('unit_name')) {
            $unitName = Unit::normalizeName((string) $this->input('unit_name'));

            $this->merge([
                'unit_name' => $unitName,
                'unit_name_normalized' => strtolower($unitName),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'unit_name_normalized.unique' => 'A unit with this name already exists for this business.',
        ];
    }
}
