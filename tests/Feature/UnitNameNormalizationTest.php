<?php

namespace Tests\Feature;

use App\Models\Unit;
use Tests\TestCase;

class UnitNameNormalizationTest extends TestCase
{
    public function test_unit_names_are_trimmed_and_normalized_without_changing_their_type(): void
    {
        $this->assertSame('Pieces', Unit::normalizeName('  Pieces  '));
        $this->assertSame('Large Carton', Unit::normalizeName("Large\t Carton"));
        $this->assertSame('pieces', strtolower(Unit::normalizeName(' PIECES ')));
    }
}
