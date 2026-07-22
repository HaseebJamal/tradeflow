<?php

namespace App\Http\Requests\Auth;

use App\Services\BusinessDocumentVerifier;
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
        $this->merge(['phone' => trim((string) $this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop', 'Other'])],
            'business_description' => ['nullable', 'string', 'max:1000', 'required_if:business_type,Other'],
            'business_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'selected_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where(fn ($query) => $query->where('status', 'Active')->where('is_public', true)->whereNull('archived_at'))],
            'billing_cycle' => ['required', Rule::in(['Monthly', 'Yearly'])],
            'cnic_image' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
            'business_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
            'shop_image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid international phone number including its country code.',
            'name.regex' => 'Name may contain letters and spaces only.',
            'business_name.regex' => 'Business name may contain letters and spaces only.',
            'city.regex' => 'City may contain letters and spaces only.',
            'business_description.required_if' => 'Please briefly describe the business when selecting Other.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Password and confirm password do not match.',
            'cnic_image.required' => 'CNIC upload is required.',
            'cnic_image.file' => 'Please upload a valid CNIC document.',
            'cnic_image.mimes' => 'Please upload a valid CNIC document.',
            'cnic_image.mimetypes' => 'Please upload a valid CNIC document.',
            'cnic_image.max' => 'CNIC document must not exceed 5 MB.',
            'business_document.required' => 'Business document upload is required.',
            'business_document.file' => 'Please upload a valid business document.',
            'business_document.mimes' => 'Please upload a valid business document.',
            'business_document.mimetypes' => 'Please upload a valid business document.',
            'business_document.max' => 'Business document must not exceed 5 MB.',
            'shop_image.required' => 'Shop or business image upload is required.',
            'shop_image.file' => 'Please upload a valid shop or business premises image.',
            'shop_image.mimes' => 'Please upload a valid shop or business premises image.',
            'shop_image.mimetypes' => 'Please upload a valid shop or business premises image.',
            'shop_image.max' => 'Shop or business premises image must not exceed 5 MB.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $verifier = app(BusinessDocumentVerifier::class);
            $hashes = [];

            foreach (['cnic_image', 'business_document', 'shop_image'] as $field) {
                if ($validator->errors()->has($field) || !$this->hasFile($field)) {
                    continue;
                }

                $file = $this->file($field);
                $message = $verifier->validate($file, $field);
                if ($message) {
                    $validator->errors()->add($field, $message);
                    continue;
                }

                $hash = $verifier->hash($file);
                if ($hash && isset($hashes[$hash])) {
                    $validator->errors()->add($field, 'Each verification field must contain its own document or image.');
                    continue;
                }

                if ($hash) {
                    $hashes[$hash] = $field;
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $step = 1;
        $errors = array_keys($validator->errors()->toArray());

        if (collect($errors)->contains(fn (string $field) => $field === 'business_type')) {
            $step = 2;
        } elseif (collect($errors)->contains(fn (string $field) => in_array($field, ['business_name', 'address', 'city', 'registration_number', 'tax_number'], true))) {
            $step = 3;
        } elseif (collect($errors)->contains(fn (string $field) => in_array($field, ['selected_plan_id', 'billing_cycle'], true))) {
            $step = 4;
        } elseif (collect($errors)->contains(fn (string $field) => in_array($field, ['cnic_image', 'business_document', 'shop_image'], true))) {
            $step = 5;
        }

        session()->flash('registration_step', $step);

        parent::failedValidation($validator);
    }
}
