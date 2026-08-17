<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

/**
 * Keeps customer contact identifiers unique inside one business without
 * treating a customer's display name as an identifier. Legacy records are
 * deliberately left untouched; this is a boundary check for new changes.
 */
class CustomerIdentityService
{
    /** @param array<string, mixed> $attributes */
    public function assertAvailable(int $businessId, array $attributes, ?int $ignoreCustomerId = null): void
    {
        $phone = $this->phone($attributes['phone'] ?? null);
        $email = $this->email($attributes['email'] ?? null);

        if ($phone === '' && $email === '') {
            return;
        }

        $duplicate = Customer::withTrashed()
            ->where('business_id', $businessId)
            ->when($ignoreCustomerId, fn ($query) => $query->whereKeyNot($ignoreCustomerId))
            ->where(function ($query) use ($phone, $email): void {
                if ($phone !== '') {
                    $query->where('phone', $phone);
                }
                if ($email !== '') {
                    $query->{$phone !== '' ? 'orWhereRaw' : 'whereRaw'}('LOWER(email) = ?', [$email]);
                }
            })
            ->first(['id', 'phone', 'email']);

        if (! $duplicate) {
            return;
        }

        $field = $phone !== '' && $phone === $this->phone($duplicate->phone) ? 'phone' : 'email';
        throw ValidationException::withMessages([
            $field => 'A customer with this '.$field.' already exists for this business.',
        ]);
    }

    private function phone(mixed $value): string
    {
        return trim((string) $value);
    }

    private function email(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
