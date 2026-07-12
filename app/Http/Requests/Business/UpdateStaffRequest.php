<?php

namespace App\Http\Requests\Business;

use App\Models\StaffProfile;
use App\Support\BusinessStaffRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business_id !== null
            && in_array($this->user()?->role, ['business_owner', 'business_admin'], true);
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff?->id)],
            'cnic' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(array_keys(BusinessStaffRoles::ROLES))],
            'custom_role_name' => ['nullable', 'string', 'max:100', 'required_if:role,custom_staff'],
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

    public function after(): array
    {
        return [function ($validator) {
            $staff = $this->route('staff');
            if (!$this->filled('employee_id') || !$this->user()?->business_id) {
                return;
            }

            $exists = StaffProfile::query()
                ->where('employee_id', $this->string('employee_id')->toString())
                ->whereHas('user', fn ($query) => $query->where('business_id', $this->user()->business_id))
                ->when($staff, fn ($query) => $query->where('user_id', '!=', $staff->id))
                ->exists();

            if ($exists) {
                $validator->errors()->add('employee_id', 'This employee ID is already in use for your business.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Password and confirm password do not match.',
            'custom_role_name.required_if' => 'Enter a name for the custom staff role.',
        ];
    }
}
