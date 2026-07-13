<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'super_admin'; }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop'])],
            'category' => ['nullable', 'string', 'max:100'],
            'company_phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'], 'city' => ['required', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'], 'tax_number' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'], 'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['required', 'string', 'max:20'],
            'temporary_password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permission_definitions', 'permission_key')],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'business_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_email.unique' => 'This owner email address is already registered.',
            'temporary_password.confirmed' => 'Password and confirm password do not match.',
        ];
    }
}
