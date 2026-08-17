<?php

namespace Tests\Unit;

use App\Models\BusinessDocumentFooter;
use App\Models\Payment;
use App\Services\DailyClosingReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DailyClosingReportServiceTest extends TestCase
{
    public function test_split_payment_components_remain_separate_in_the_daily_breakdown(): void
    {
        $service = app(DailyClosingReportService::class);
        $breakdown = new \ReflectionMethod($service, 'breakdown');

        $result = $breakdown->invoke($service, collect([
            new Payment(['method' => 'Cash', 'amount' => 4000]),
            new Payment(['method' => 'Card', 'amount' => 6000]),
        ]));

        $this->assertSame(['Card' => 6000.0, 'Cash' => 4000.0], $result);
        $this->assertSame(10000.0, array_sum($result));
        $this->assertSame(4000.0, $result['Cash']);
    }

    public function test_daily_closing_pdf_renders_canonical_totals_and_open_register_state(): void
    {
        $footer = new BusinessDocumentFooter([
            'footer_title' => 'Apex Foods', 'footer_message' => 'Thank you for your business.',
            'show_footer_title' => true, 'show_footer_message' => true,
            'show_address' => false, 'show_phone' => false, 'show_email' => false, 'show_website' => false,
            'show_powered_by' => true,
        ]);
        $business = (object) ['business_name' => 'Apex Foods', 'documentFooter' => $footer, 'owner' => (object) ['email' => 'owner@example.test']];
        $date = Carbon::parse('2026-08-16', config('app.timezone'));
        $report = [
            'status' => 'Open',
            'sales' => ['invoice_count' => 1, 'gross_sales' => 100000, 'line_discounts_included' => 0, 'invoice_discounts' => 5000, 'total_discounts' => 5000, 'sales_returns' => 5000, 'net_sales' => 90000, 'paid_sales' => 10000, 'credit_sales' => 80000],
            'payments' => ['breakdown' => ['Card' => 6000, 'Cash' => 4000], 'customer_collections' => 5000],
            'expenses' => ['total' => 10000],
            'profitability' => ['net_sales' => 90000, 'cogs' => 55000, 'gross_profit' => 35000, 'expenses' => 10000, 'net_profit' => 25000],
            'purchases' => ['amount' => 1000, 'supplier_payments' => 400, 'purchase_returns' => 0, 'grn_count' => 1],
            'registers' => ['open_count' => 1, 'rows' => [[
                'cashier' => 'Cashier', 'opened_at' => $date->copy()->setTime(9, 0), 'closed_at' => null,
                'expected_cash' => 15300, 'actual_cash' => null, 'variance' => null,
            ]]],
        ];

        $html = Blade::render("@include('business.reports.end-of-day-pdf')", [
            'business' => $business, 'selectedDate' => $date, 'generatedAt' => $date->copy()->setTime(18, 30),
            'report' => $report, 'canPos' => true, 'canPurchases' => true,
        ]);

        $this->assertStringContainsString('End of Day Report', $html);
        $this->assertStringContainsString('Rs 90,000.00', $html);
        $this->assertStringContainsString('Card', $html);
        $this->assertStringContainsString('Daily cash reconciliation is incomplete', $html);
        $this->assertStringContainsString('Rs 25,000.00', $html);

        $pdf = Pdf::loadHtml($html)->setPaper('a4')->output();
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_daily_closing_composes_the_existing_profitability_service_without_writes(): void
    {
        $source = file_get_contents(app_path('Services/DailyClosingReportService.php'));

        $this->assertStringContainsString('$this->profitability->forPeriod', $source);
        $this->assertStringNotContainsString('->update(', $source);
        $this->assertStringNotContainsString('::create(', $source);
    }
}
