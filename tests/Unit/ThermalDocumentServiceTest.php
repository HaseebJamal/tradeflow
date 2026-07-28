<?php

namespace Tests\Unit;

use App\Models\BusinessDocumentFooter;
use App\Services\ThermalDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ThermalDocumentServiceTest extends TestCase
{
    public function test_it_defaults_to_80mm_and_supports_a_58mm_override(): void
    {
        $service = new ThermalDocumentService;

        $this->assertSame(80, $service->width(Request::create('/business/pos/receipts/INV-1/view')));
        $this->assertSame(58, $service->width(Request::create('/business/pos/receipts/INV-1/view?paper=58')));
        $this->assertEqualsWithDelta(80 * (72 / 25.4), $service->dompdfPaper(80)[2], 0.001);
        $this->assertEqualsWithDelta(58 * (72 / 25.4), $service->dompdfPaper(58)[2], 0.001);
    }

    public function test_it_sizes_thermal_pdf_paper_from_rendered_content(): void
    {
        $service = new ThermalDocumentService;
        $estimate = new \ReflectionMethod($service, 'estimatePaperHeight');

        $shortReceipt = <<<'HTML'
            <section class="tf-thermal-document">
                <div class="tf-thermal-document__row"></div>
                <div class="tf-thermal-document__item"><div class="tf-thermal-document__item-name"></div><span class="tf-thermal-document__item-calculation"></span></div>
                <div class="tf-thermal-document__row tf-thermal-document__total"></div>
                <footer class="tf-document-footer"><div>Thank you</div></footer>
            </section>
            HTML;

        $longReceipt = str_replace(
            '<footer',
            str_repeat('<div class="tf-thermal-document__item"><div class="tf-thermal-document__item-name"></div></div>', 15).'<footer',
            $shortReceipt
        );

        $shortHeight = $estimate->invoke($service, $shortReceipt, 80);
        $longHeight = $estimate->invoke($service, $longReceipt, 80);

        $this->assertLessThan(297, $shortHeight);
        $this->assertGreaterThanOrEqual(75, $shortHeight);
        $this->assertGreaterThan($shortHeight, $longHeight);
    }

    public function test_the_shared_thermal_document_produces_visible_pdf_content(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-thermal-document
                :business="$business"
                title="POS Receipt"
                number="INV-000001"
                date="27 Jul 2026 12:00 PM"
                cashier="Cashier"
                party-label="Customer"
                party-name="Walk-in Customer"
                :items="$items"
                :totals="$totals"
                :pdf="true"
            />
            BLADE, [
            'business' => (object) ['name' => 'TradeFlow Test', 'address' => 'Lahore', 'phone' => '+923001234567', 'tax_number' => 'NTN-123'],
            'items' => [['name' => 'Thermal test item', 'quantity' => '2', 'rate' => 'Rs 50.00', 'amount' => 'Rs 100.00']],
            'totals' => [['label' => 'Grand total', 'amount' => 'Rs 100.00', 'emphasis' => true]],
        ]);

        $this->assertStringContainsString('INV-000001', $html);
        $this->assertStringContainsString('Thermal test item', $html);
        $this->assertStringContainsString('2 &times; Rs 50.00', $html);
        $this->assertStringContainsString('tf-thermal-document__item-details', $html);
        $this->assertStringNotContainsString('<table class="tf-thermal-document__items">', $html);
        $this->assertStringContainsString('@page { margin: 3mm; }', $html);
        $this->assertStringContainsString('max-width: 74mm', $html);
        $this->assertStringContainsString('Lahore', $html);
        $this->assertStringContainsString('+923001234567', $html);
        $this->assertStringContainsString('Tax / NTN: NTN-123', $html);
        $this->assertStringNotContainsString('Cashier: Cashier', $html);

        $pdf = Pdf::loadHtml('<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>')
            ->setPaper((new ThermalDocumentService)->dompdfPaper(80))
            ->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);
        $streams = collect($matches[1] ?? [])
            ->map(fn (string $stream) => @gzuncompress($stream) ?: $stream)
            ->implode("\n");

        $this->assertStringContainsString('BT ', $streams);
        $this->assertStringContainsString("\0T\0r\0a\0d\0e\0F\0l\0o\0w\0 \0T\0e\0s\0t", $streams);
        $this->assertStringContainsString("\0T\0h\0e\0r\0m\0a\0l\0 \0t\0e\0s\0t\0 \0i\0t\0e\0m", $streams);
    }

    public function test_document_footer_uses_company_data_and_hides_disabled_fields(): void
    {
        $footer = new BusinessDocumentFooter([
            'footer_title' => 'Apex Foods',
            'footer_message' => 'Thank you, Lahore!',
            'show_company_name' => true,
            'show_footer_title' => true,
            'show_footer_message' => true,
            'show_address' => true,
            'show_phone' => false,
            'show_email' => true,
            'show_website' => true,
            'show_tax_number' => false,
            'show_powered_by' => true,
        ]);

        $html = Blade::render('<x-document-footer :business="$business" :footer="$footer" />', [
            'business' => (object) [
                'business_name' => 'Apex Foods',
                'address' => 'Model Town',
                'city' => 'Lahore',
                'phone' => '+923001234567',
                'owner' => (object) ['email' => 'owner@example.test'],
                'tax_number' => 'NTN-123',
            ],
            'footer' => $footer,
        ]);

        $this->assertStringContainsString('Thank you, Lahore!', $html);
        $this->assertStringContainsString('Model Town, Lahore', $html);
        $this->assertStringContainsString('owner@example.test', $html);
        $this->assertStringContainsString((string) now()->year, $html);
        $this->assertStringNotContainsString('+923001234567', $html);
        $this->assertStringNotContainsString('NTN-123', $html);
    }
}
