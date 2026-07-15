<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'super_admin'; }

    public function rules(): array
    {
        $company = $this->route('company');
        return [
            'business_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop', 'Other'])],
            'business_description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['required', 'regex:/^\d{11}$/'],
            'address' => ['required', 'string', 'max:1000'], 'city' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'registration_number' => ['nullable', 'string', 'max:100'], 'tax_number' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($company?->owner_id)],
            'owner_phone' => ['required', 'regex:/^\d{11}$/'],
            'owner_password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_company_logo' => ['nullable', 'boolean'],
            'business_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
