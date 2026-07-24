<?php

namespace Tests\Feature;

use App\Services\PhoneNumberService;
use Tests\TestCase;

class PhoneNumberServiceTest extends TestCase
{
    public function test_valid_international_numbers_are_normalized_to_e164(): void
    {
        $phones = app(PhoneNumberService::class);

        $this->assertSame('+923001234567', $phones->normalize('+92 300-1234567'));
        $this->assertSame('+447700900123', $phones->normalize('0044 7700 900123'));
        $this->assertSame('447700900123', $phones->whatsappDigits('+44 7700 900123'));
    }

    public function test_malformed_or_unknown_calling_codes_are_not_accepted(): void
    {
        $phones = app(PhoneNumberService::class);

        $this->assertSame('invalid-phone-number', $phones->normalize('+99912345678'));
        $this->assertSame('abc123', $phones->normalize('abc123'));
        $this->assertFalse($phones->isValidE164('+99912345678'));
    }

    public function test_legacy_pakistani_local_numbers_are_only_normalized_by_the_backfill_path(): void
    {
        $phones = app(PhoneNumberService::class);

        $this->assertSame('03001234567', $phones->normalize('03001234567'));
        $this->assertSame('+923001234567', $phones->normalizeLegacyPakistaniNumber('03001234567'));
    }
}
