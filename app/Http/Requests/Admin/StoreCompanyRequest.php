<?php

namespace App\Http\Requests\Admin;

use App\Models\AuditLog;
use App\Services\AuditIpResolver;
use App\Models\PermissionDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'super_admin'; }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u', 'unique:businesses,business_name'],
            'business_type' => ['required', Rule::in(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop', 'Other'])],
            'business_description' => ['nullable', 'string', 'max:1000', 'required_if:business_type,Other'],
            'company_phone' => ['required', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'address' => ['required', 'string', 'max:500'], 'city' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'registration_number' => ['nullable', 'string', 'max:100'], 'tax_number' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'], 'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['required', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'temporary_password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            // Company modules are optional at creation time. Super Admin can
            // assign or change them later from Company Permissions.
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permission_definitions', 'permission_key')],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'owner_profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cnic_image' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'business_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'shop_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_email.unique' => 'This owner email address is already registered.',
            'business_name.unique' => 'A company with this name already exists.',
            'business_name.regex' => 'Company name may contain letters and spaces only.',
            'owner_name.regex' => 'Owner name may contain letters and spaces only.',
            'city.regex' => 'City may contain letters and spaces only.',
            'company_phone.regex' => 'Company phone must be a valid international number including its country code.',
            'owner_phone.regex' => 'Owner phone must be a valid international number including its country code.',
            'business_description.required_if' => 'Describe the business when selecting Other.',
            'temporary_password.confirmed' => 'Password and confirm password do not match.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $selected = collect($this->input('permissions', []))->map(fn ($key) => strtolower((string) $key))->unique();
            if ($selected->isEmpty()) return;

            $definitions = PermissionDefinition::where('status', 'active')->get(['module', 'permission_key']);
            $activeKeys = $definitions->pluck('permission_key')->map(fn ($key) => strtolower($key));
            if ($selected->diff($activeKeys)->isNotEmpty()) {
                $validator->errors()->add('permissions', 'One or more selected permissions are no longer active. Refresh the form and try again.');
                return;
            }

        });
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->user()) {
            AuditLog::create([
                'user_id' => $this->user()->id,
                'actor_id' => $this->user()->id,
                'actor_role' => $this->user()->role,
                'module' => 'Companies',
                'action' => 'company creation failed',
                'description' => 'Company creation validation failed: '.implode(' ', $validator->errors()->all()),
                'new_values' => $this->only([
                    'business_name', 'business_type', 'business_description', 'company_phone',
                    'address', 'city', 'registration_number', 'tax_number', 'owner_name',
                    'owner_email', 'owner_phone', 'permissions', 'notes',
                ]),
                'ip_address' => app(AuditIpResolver::class)->capture($this),
                'user_agent' => substr((string) $this->userAgent(), 0, 1000),
            ]);
        }

        parent::failedValidation($validator);
    }
}
