<?php

namespace Tests\Unit;

use App\Models\BusinessDocumentFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class A4DocumentComponentTest extends TestCase
{
    public function test_it_renders_a_print_safe_branded_a4_document_and_footer(): void
    {
        $footer = new BusinessDocumentFooter([
            'footer_title' => 'Apex Foods',
            'footer_message' => 'Thank you for your business.',
            'show_footer_title' => true,
            'show_footer_message' => true,
            'show_address' => false,
            'show_phone' => false,
            'show_email' => false,
            'show_website' => false,
            'show_powered_by' => true,
        ]);

        $html = Blade::render(<<<'BLADE'
            <x-a4-document :business="$business" :footer="$footer" title="Customer Aging Report" reference="As of 8/16/2026" date="8/16/2026, 6:30 PM" status="Current">
                <table class="tf-a4-document__table"><thead><tr><th>Party</th><th class="tf-a4-document__money">Outstanding</th></tr></thead><tbody><tr><td>Very Long Customer Name</td><td class="tf-a4-document__money">Rs 1,500.00</td></tr></tbody></table>
            </x-a4-document>
            BLADE, [
            'business' => (object) ['business_name' => 'Apex Foods', 'phone' => '+923001234567', 'owner' => (object) ['email' => 'owner@example.test']],
            'footer' => $footer,
        ]);

        $this->assertStringContainsString('Customer Aging Report', $html);
        $this->assertStringContainsString('Very Long Customer Name', $html);
        $this->assertStringContainsString('tf-a4-document__header-table', $html);
        $this->assertStringContainsString('thead { display: table-header-group; }', $html);
        $this->assertStringContainsString('page-break-inside: avoid;', $html);
        $this->assertStringContainsString('Thank you for your business.', $html);
        $this->assertStringContainsString(
            app(\App\Services\BusinessDocumentFooterService::class)->displayedPoweredByText($footer),
            $html,
        );

        $pdf = Pdf::loadHtml($html)->setPaper('a4')->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }

    public function test_analytics_exports_use_the_shared_a4_document_component(): void
    {
        foreach (['aging-pdf', 'product-performance-pdf', 'stock-movement-analytics-pdf'] as $view) {
            $source = file_get_contents(resource_path('views/business/reports/'.$view.'.blade.php'));

            $this->assertStringContainsString('<x-a4-document', $source, $view.' should use the shared A4 document shell.');
            $this->assertStringNotContainsString('<footer>', $source, $view.' should keep footer behavior in the shared component.');
        }
    }
}
