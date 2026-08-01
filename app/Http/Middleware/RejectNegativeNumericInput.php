<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RejectNegativeNumericInput
{
    /**
     * Values with these names are entered as non-negative business amounts.
     * Inventory reductions and returns are represented by their action/type;
     * their submitted quantity remains positive.
     */
    private const NON_NEGATIVE_FIELDS = '/(?:^|_)(?:price|cost|quantity|qty|stock|discount|tax|payment|paid|received|amount|balance|total|credit_limit|opening_balance|salary)(?:$|_)/i';
    private const WHOLE_NUMBER_FIELDS = '/(?:^|_)(?:price|cost|quantity|qty|stock|discount|tax|payment|paid|received|amount|balance|total|credit_limit|opening_balance|salary|cash|other_charges|shipping|due|debit|credit|change)(?:$|_)/i';
    private const DATE_FIELDS = '/(?:^|_)(?:date|at)(?:$|_)/i';

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $errors = [];
        $this->collectInvalidValues($request->all(), '', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }

    private function collectInvalidValues(array $values, string $prefix, array &$errors): void
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $this->collectInvalidValues($value, $path, $errors);
                continue;
            }

            $field = (string) $key;
            $raw = is_scalar($value) ? trim((string) $value) : null;

            // Date transport fields such as payment_date and due_date contain
            // words that also appear in numeric field names. They are dates,
            // not amounts, and must be left to their route validation rules.
            if (preg_match(self::DATE_FIELDS, $field)) {
                continue;
            }

            if ($raw !== null && $raw !== '' && preg_match(self::WHOLE_NUMBER_FIELDS, $field) && preg_match('/^[\d.,+\-eE]+$/', $raw) && !preg_match('/^\d+$/', $raw)) {
                $errors[$path] = 'Only whole numbers are allowed.';
                continue;
            }

            if (!preg_match(self::NON_NEGATIVE_FIELDS, $field) || !is_numeric($value)) {
                continue;
            }

            if ((float) $value < 0) {
                $errors[$path] = 'Negative values are not allowed.';
            }
        }
    }
}
