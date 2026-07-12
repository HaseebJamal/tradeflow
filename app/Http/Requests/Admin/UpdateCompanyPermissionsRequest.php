<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyPermissionsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'super_admin'; }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:businesses,id'],
            'scope' => ['required', Rule::in(['modules', 'features', 'actions'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permission_definitions', 'permission_key')],
        ];
    }
}
