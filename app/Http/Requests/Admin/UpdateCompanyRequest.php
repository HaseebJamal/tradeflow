<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\AuditLog;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'super_admin') {
            return false;
        }

        if ($this->has('owner_email') || $this->has('owner_password') || $this->has('owner_password_confirmation')) {
            $company = $this->route('company');
            AuditLog::create([
                'user_id' => $this->user()->id,
                'actor_id' => $this->user()->id,
                'actor_role' => $this->user()->role,
                'business_id' => $company?->id,
                'module' => 'Companies',
                'action' => 'blocked company credential access',
                'description' => 'Blocked Super Admin company credential update attempt.',
                'new_values' => ['operation' => 'credential_update'],
                'ip_address' => $this->ip(),
                'user_agent' => substr((string) $this->userAgent(), 0, 1000),
            ]);
            abort(403, $this->has('owner_email')
                ? 'Owner login email cannot be changed by Super Admin.'
                : 'Super Admins cannot update company login credentials.');
        }

        return true;
    }

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
            'owner_phone' => ['required', 'regex:/^\d{11}$/'],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_company_logo' => ['nullable', 'boolean'],
            'business_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
