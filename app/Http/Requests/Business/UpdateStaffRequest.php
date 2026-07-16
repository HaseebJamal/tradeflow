<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business_id !== null
            && in_array($this->user()?->role, ['super_admin', 'business_owner'], true);
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'father_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['required', 'regex:/^\d{11}$/'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff?->id)],
            'cnic' => ['nullable', 'regex:/^\d{13}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'role' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'employment_type' => ['required', Rule::in(['Full Time', 'Part Time', 'Temporary'])],
            'joining_date' => ['required', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'archived'])],
            'password' => ['nullable', 'required_with:password_confirmation', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permission_definitions', 'permission_key')],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Password and confirm password do not match.',
            'cnic.regex' => 'CNIC must contain exactly 13 digits.',
        ];
    }
}
