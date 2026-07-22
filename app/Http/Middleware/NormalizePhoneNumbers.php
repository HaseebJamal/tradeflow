<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizePhoneNumbers
{
    private const FIELDS = ['phone', 'company_phone', 'owner_phone', 'receiver_phone', 'new_customer_phone', 'support_phone', 'contact_number', 'mobile', 'whatsapp'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $request->replace($this->normalise($request->request->all()));
        }

        return $next($request);
    }

    private function normalise(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->normalise($value);
                continue;
            }

            if (in_array(strtolower((string) $key), self::FIELDS, true) && is_string($value)) {
                $values[$key] = $this->toE164($value);
            }
        }

        return $values;
    }

    private function toE164(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';

        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($value, '+')) return '+'.$digits;
        if (str_starts_with($digits, '00')) return '+'.substr($digits, 2);
        // Preserve compatibility for existing Pakistani local-format entries.
        if (preg_match('/^0?3\d{9}$/', $digits)) return '+92'.ltrim($digits, '0');

        return '+'.$digits;
    }
}
