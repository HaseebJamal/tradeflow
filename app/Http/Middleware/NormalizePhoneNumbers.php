<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizePhoneNumbers
{
    private const FIELDS = ['phone', 'company_phone', 'owner_phone', 'receiver_phone', 'new_customer_phone', 'support_phone', 'contact_number', 'mobile', 'whatsapp'];

    public function __construct(private readonly PhoneNumberService $phones)
    {
    }

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

            if ($this->isPhoneField((string) $key) && is_string($value)) {
                $values[$key] = $this->phones->normalize($value);
            }
        }

        return $values;
    }

    private function isPhoneField(string $key): bool
    {
        $key = strtolower($key);

        return in_array($key, self::FIELDS, true)
            || str_ends_with($key, '_phone')
            || str_ends_with($key, '_mobile')
            || str_ends_with($key, '_whatsapp');
    }
}
