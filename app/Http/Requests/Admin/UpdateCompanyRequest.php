<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'super_admin'; }

    public function rules(): array
    {
        $company = $this->route('company');
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop'])],
            'category' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'], 'city' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'], 'tax_number' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($company?->owner_id)],
            'owner_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
