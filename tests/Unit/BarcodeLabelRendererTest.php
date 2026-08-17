<?php

namespace Tests\Unit;

use Picqer\Barcode\BarcodeGeneratorSVG;
use Tests\TestCase;

class BarcodeLabelRendererTest extends TestCase
{
    public function test_it_generates_a_code_128_svg_for_the_existing_barcode_value(): void
    {
        $generator = new BarcodeGeneratorSVG();
        $barcode = '1000000012';

        $svg = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 1.6, 42);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<desc>'.$barcode.'</desc>', $svg);
        $this->assertStringContainsString('fill="rgb(0,0,0)"', $svg);
    }
}
