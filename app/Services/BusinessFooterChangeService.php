<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessDocumentFooter;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BusinessFooterChangeService
{
    /** @return array<string, string> */
    public function fields(): array
    {
        return [
            'business_name' => 'Company Name',
            'address' => 'Address',
            'phone' => 'Phone',
            'business_email' => 'Business Email',
            'website' => 'Website',
        ];
    }

    public function currentValue(Business $business, BusinessDocumentFooter $footer, string $field): ?string
    {
        return match ($field) {
            'business_email' => $business->owner?->email,
            default => $business->{$field},
        };
    }

    public function normalize(string $field, mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages(['requested_value' => 'Provide the requested value.']);
        }

        if ($field === 'phone' && ! preg_match('/^\+[1-9]\d{7,14}$/', $value)) {
            throw ValidationException::withMessages(['requested_value' => 'Enter a valid international phone number.']);
        }

        if ($field === 'business_email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['requested_value' => 'Enter a valid business email address.']);
        }

        if ($field === 'website' && ! filter_var($value, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['requested_value' => 'Enter a valid website URL.']);
        }

        return $value;
    }

    public function apply(Business $business, BusinessDocumentFooter $footer, string $field, ?string $value): void
    {
        switch ($field) {
            case 'business_email':
                $owner = $business->owner;
                if (! $owner) {
                    throw ValidationException::withMessages(['request' => 'This company does not have an owner account to update.']);
                }
                $exists = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $value)])
                    ->where('id', '!=', $owner->id)
                    ->exists();
                if ($exists) {
                    throw ValidationException::withMessages(['request' => 'That business email is already in use.']);
                }
                $owner->update(['email' => $value]);
                break;
            case 'show_powered_by':
                $footer->update(['show_powered_by' => true]);
                break;
            case 'powered_by_text':
                $footer->update(['powered_by_text' => null, 'show_powered_by' => true]);
                break;
            default:
                $business->update([$field => $value]);
                break;
        }
    }
}
