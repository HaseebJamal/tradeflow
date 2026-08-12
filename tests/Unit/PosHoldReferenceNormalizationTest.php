<?php

namespace Tests\Unit;

use App\Services\PosSaleService;
use Tests\TestCase;

class PosHoldReferenceNormalizationTest extends TestCase
{
    public function test_supported_hold_entries_share_one_canonical_reference(): void
    {
        $service = app(PosSaleService::class);

        $this->assertSame('HOLD-000011', $service->normalizeHoldNumber('11'));
        $this->assertSame('HOLD-000011', $service->normalizeHoldNumber('000011'));
        $this->assertSame('HOLD-000011', $service->normalizeHoldNumber('hold-000011'));
    }
}
