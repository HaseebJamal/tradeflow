<?php

namespace Tests\Unit;

use App\Models\BusinessDocumentFooter;
use App\Services\BusinessDocumentFooterService;
use Illuminate\Http\Request;
use Tests\TestCase;

class BusinessDocumentFooterVisibilityTest extends TestCase
{
    public function test_checked_business_name_is_normalized_to_true(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT', [
                'footer_visibility' => ['show_company_name' => '1'],
            ]));

        $this->assertTrue($visibility['show_company_name']);
    }

    public function test_missing_business_name_checkbox_is_normalized_to_false(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT'));

        $this->assertFalse($visibility['show_company_name']);
        $this->assertSame(BusinessDocumentFooter::VISIBILITY_FIELDS, array_keys($visibility));
    }

    public function test_explicit_unchecked_business_name_is_normalized_to_false(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT', [
                'footer_visibility' => ['show_company_name' => '0'],
            ]));

        $this->assertFalse($visibility['show_company_name']);
    }
}
