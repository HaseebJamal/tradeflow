<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterBusinessRequest extends FormRequest
{
    /** @var array<int, string> */
    private const CLIENT_CONTROLLED_TRIAL_FIELDS = ['trial_days', 'trial_end', 'trial_end_at', 'access_duration', 'access_end', 'access_end_at'];
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => trim((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'business_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach (self::CLIENT_CONTROLLED_TRIAL_FIELDS as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'Trial access is configured securely by Profit Point after registration.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid international phone number including its country code.',
            'name.regex' => 'Name may contain letters and spaces only.',
            'city.regex' => 'City may contain letters and spaces only.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'Password and confirm password do not match.',
            'logo.mimes' => 'Upload a JPG, PNG, or WebP logo.',
            'logo.max' => 'The logo must not exceed 2 MB.',
        ];
    }
}
