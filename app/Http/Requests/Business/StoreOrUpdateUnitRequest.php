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
        $typeRules = [
            'required',
            Rule::in(['Piece', 'Weight', 'Volume', 'Length', 'Area', 'Pack', 'Other']),
        ];

        // Keep legacy duplicate records editable, while preventing a new type conflict.
        if (! $unit instanceof Unit || $this->input('unit_type') !== $unit->unit_type) {
            $typeRules[] = Rule::unique('units', 'unit_type')
                ->where(fn ($query) => $query->where('business_id', $this->user()->business_id))
                ->ignore($unitId);
        }

        return [
            'unit_name' => ['required', 'string', 'max:100'],
            'unit_type' => $typeRules,
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_type.unique' => 'This unit already exists for this business.',
        ];
    }
}
