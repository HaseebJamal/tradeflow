<?php

namespace App\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberService
{
    private const COUNTRY_CALLING_CODES = [
        1, 7, 20, 27, 30, 31, 32, 33, 34, 36, 39, 40, 41, 43, 44, 45, 46, 47, 48, 49,
        51, 52, 53, 54, 55, 56, 57, 58, 60, 61, 62, 63, 64, 65, 66, 81, 82, 84, 86,
        90, 91, 92, 93, 94, 95, 98, 211, 212, 213, 216, 218, 220, 221, 222, 223, 224,
        225, 226, 227, 228, 229, 230, 231, 232, 233, 234, 235, 236, 237, 238, 239, 240,
        241, 242, 243, 244, 245, 246, 248, 249, 250, 251, 252, 253, 254, 255, 256, 257,
        258, 260, 261, 262, 263, 264, 265, 266, 267, 268, 269, 290, 291, 297, 298, 299,
        350, 351, 352, 353, 354, 355, 356, 357, 358, 359, 370, 371, 372, 373, 374, 375,
        376, 377, 378, 379, 380, 381, 382, 383, 385, 386, 387, 389, 420, 421, 423, 500,
        501, 502, 503, 504, 505, 506, 507, 508, 509, 590, 591, 592, 593, 594, 595, 596,
        597, 598, 599, 670, 672, 673, 674, 675, 676, 677, 678, 679, 680, 681, 682, 683,
        685, 686, 687, 688, 689, 690, 691, 692, 850, 852, 853, 855, 856, 880, 886, 960,
        961, 962, 963, 964, 965, 966, 967, 968, 970, 971, 972, 973, 974, 975, 976, 977,
        992, 993, 994, 995, 996, 998,
    ];

    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^(?:\+|00)[0-9\s().-]+$/', $value)) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $value);
        $number = str_starts_with($value, '00') ? '+'.substr($digits, 2) : '+'.$digits;

        return $this->isValidE164($number) ? $number : 'invalid-phone-number';
    }

    public function isValidE164(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\+[1-9]\d{7,14}$/', $value)) {
            return false;
        }

        $utility = $this->phoneNumberUtility();
        if (! $utility) {
            return $this->hasKnownCountryCallingCode($value);
        }

        try {
            $number = $utility->parse($value, null);

            // A business system can validate an actual country calling code
            // and national number length/structure, but must not reject a
            // customer's number merely because an allocation database cannot
            // confirm that it is currently assigned to a carrier.
            return $utility->isPossibleNumber($number)
                && $utility->format($number, PhoneNumberFormat::E164) === $value;
        } catch (NumberParseException) {
            return false;
        }
    }

    private function phoneNumberUtility(): ?PhoneNumberUtil
    {
        if (! class_exists(PhoneNumberUtil::class)) {
            $sourceDirectory = base_path('vendor/giggsey/libphonenumber-for-php/src');
            if (is_dir($sourceDirectory)) {
                // This package is a declared Composer dependency. Register a
                // local fallback only when an in-place Composer autoload cache
                // is stale, so web requests continue to validate safely.
                spl_autoload_register(static function (string $class) use ($sourceDirectory): void {
                    $prefix = 'libphonenumber\\';
                    if (! str_starts_with($class, $prefix)) {
                        return;
                    }

                    $path = $sourceDirectory.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))).'.php';
                    if (is_file($path)) {
                        require_once $path;
                    }
                });
            }
        }

        return class_exists(PhoneNumberUtil::class) ? PhoneNumberUtil::getInstance() : null;
    }

    private function hasKnownCountryCallingCode(string $value): bool
    {
        $digits = substr($value, 1);
        foreach ([3, 2, 1] as $length) {
            $callingCode = (int) substr($digits, 0, $length);
            if (in_array($callingCode, self::COUNTRY_CALLING_CODES, true)) {
                return true;
            }
        }

        return false;
    }

    public function normalizeLegacyPakistaniNumber(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $normalized = $this->normalize($value);
        if ($this->isValidE164($normalized)) {
            return $normalized;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (preg_match('/^0?3\d{9}$/', $digits)) {
            return '+92'.ltrim($digits, '0');
        }

        return null;
    }

    public function whatsappDigits(?string $value): ?string
    {
        $normalized = $this->normalize($value);

        return $this->isValidE164($normalized) ? substr($normalized, 1) : null;
    }
}
