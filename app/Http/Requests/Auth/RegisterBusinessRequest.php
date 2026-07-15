<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s-]+/', '', (string) $this->input('phone'));

        if (str_starts_with($phone, '+92')) {
            $phone = '0'.substr($phone, 3);
        } elseif (str_starts_with($phone, '92')) {
            $phone = '0'.substr($phone, 2);
        }

        $this->merge(['phone' => $phone]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['required', 'regex:/^03\d{9}$/'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop', 'Other'])],
            'business_description' => ['nullable', 'string', 'max:1000', 'required_if:business_type,Other'],
            'business_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'cnic_image' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'business_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'shop_image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Pakistani phone number, for example 03001234567.',
            'name.regex' => 'Name may contain letters and spaces only.',
            'business_name.regex' => 'Business name may contain letters and spaces only.',
            'city.regex' => 'City may contain letters and spaces only.',
            'business_description.required_if' => 'Please briefly describe the business when selecting Other.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Password and confirm password do not match.',
            'cnic_image.required' => 'CNIC upload is required.',
            'business_document.required' => 'Business document upload is required.',
            'shop_image.required' => 'Shop or business image upload is required.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $step = 1;
        $errors = array_keys($validator->errors()->toArray());

        if (collect($errors)->contains(fn (string $field) => $field === 'business_type')) {
            $step = 2;
        } elseif (collect($errors)->contains(fn (string $field) => in_array($field, ['business_name', 'address', 'city', 'registration_number', 'tax_number'], true))) {
            $step = 3;
        } elseif (collect($errors)->contains(fn (string $field) => in_array($field, ['cnic_image', 'business_document', 'shop_image'], true))) {
            $step = 4;
        }

        session()->flash('registration_step', $step);

        parent::failedValidation($validator);
    }
}
