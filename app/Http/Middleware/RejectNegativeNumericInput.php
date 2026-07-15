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

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $errors = [];
        $this->collectNegativeValues($request->all(), '', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }

    private function collectNegativeValues(array $values, string $prefix, array &$errors): void
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $this->collectNegativeValues($value, $path, $errors);
                continue;
            }

            if (!preg_match(self::NON_NEGATIVE_FIELDS, (string) $key) || !is_numeric($value)) {
                continue;
            }

            if ((float) $value < 0) {
                $errors[$path] = 'Negative values are not allowed.';
            }
        }
    }
}
