<?php

namespace App\Services;

use Illuminate\Http\Request;

class CustomerFinancialFieldService
{
    public const FIELDS = ['credit_limit', 'opening_balance'];

    /**
     * Empty customer financial fields are always persisted as zero. Non-empty
     * values remain untouched so Laravel validation can reject invalid input.
     */
    public function normalizeRequest(Request $request, bool $includeMissing = false): void
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            if (! $includeMissing && ! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);
            $normalized[$field] = $value === null || trim((string) $value) === '' ? 0 : $value;
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    /**
     * Shared rule for customer amounts. The regex deliberately rejects decimal
     * and scientific notation before values reach accounting workflows.
     */
    public function wholeNumberRules(): array
    {
        return ['required', 'regex:/^\d+$/', 'integer', 'min:0'];
    }

    public function normalizeArray(array $values): array
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $values) && ($values[$field] === null || trim((string) $values[$field]) === '')) {
                $values[$field] = 0;
            }
        }

        return $values;
    }
}
