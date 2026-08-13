<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\RenewalInvoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RenewalInvoicePdfTemplateTest extends TestCase
{
    public function test_renewal_invoice_renders_as_an_a4_billing_document(): void
    {
        $business = new Business;
        $business->forceFill(['business_name' => 'CareX', 'phone' => '+923452678956']);

        $owner = new User;
        $owner->forceFill(['name' => 'Steve', 'email' => 'steve@example.com']);

        $invoice = new RenewalInvoice;
        $invoice->forceFill([
            'invoice_number' => 'PP-RNW-10-20260816',
            'amount' => 4999,
            'access_starts_at' => '2026-08-12',
            'access_ends_at' => '2026-08-16',
            'due_date' => '2026-08-16',
            'last_payment_method' => 'Jazz Cash',
            'status' => RenewalInvoice::STATUS_PENDING_PAYMENT,
        ]);
        $invoice->created_at = Carbon::parse('2026-08-12 18:30:00', config('app.timezone'));

        $settings = new PlatformSetting;
        $settings->forceFill(['company_name' => 'Profit Point', 'support_email' => 'support@profitpoint.test']);

        $html = view('super-admin.renewal-invoices.pdf', [
            'invoice' => $invoice,
            'business' => $business,
            'owner' => $owner,
            'platformName' => 'Profit Point',
            'platformSettings' => $settings,
            'platformLogoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('RENEWAL INVOICE', $html);
        $this->assertStringContainsString('Access Renewal Notice', $html);
        $this->assertStringContainsString('Rs 4,999.00', $html);
        $this->assertStringNotContainsString('This is a renewal invoice, not a payment receipt.', $html);
        $this->assertNotEmpty(Pdf::loadHtml($html)->setPaper('a4')->output());
    }
}
