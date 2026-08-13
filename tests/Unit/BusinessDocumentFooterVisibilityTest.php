<?php

namespace Tests\Unit;

use App\Models\BusinessDocumentFooter;
use App\Services\BusinessDocumentFooterService;
use Illuminate\Http\Request;
use Tests\TestCase;

class BusinessDocumentFooterVisibilityTest extends TestCase
{
    public function test_business_name_is_not_an_available_footer_visibility_option(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT', [
                'footer_visibility' => ['show_company_name' => '1'],
            ]));

        $this->assertArrayNotHasKey('show_company_name', $visibility);
        $this->assertSame(BusinessDocumentFooter::VISIBILITY_FIELDS, array_keys($visibility));
    }

    public function test_missing_visibility_options_are_normalized_to_false(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT'));

        $this->assertFalse($visibility['show_footer_title']);
        $this->assertSame(BusinessDocumentFooter::VISIBILITY_FIELDS, array_keys($visibility));
    }

    public function test_explicit_unchecked_footer_title_is_normalized_to_false(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT', [
                'footer_visibility' => ['show_footer_title' => '0'],
            ]));

        $this->assertFalse($visibility['show_footer_title']);
    }

    public function test_checked_footer_title_is_normalized_to_true_independently(): void
    {
        $visibility = app(BusinessDocumentFooterService::class)
            ->visibilityFromRequest(Request::create('/', 'PUT', [
                'footer_visibility' => [
                    'show_footer_title' => '1',
                ],
            ]));

        $this->assertTrue($visibility['show_footer_title']);
    }

    public function test_document_footer_title_renders_when_it_matches_the_business_name(): void
    {
        $business = new \App\Models\Business([
            'business_name' => 'Clementine Stein',
        ]);
        $footer = new BusinessDocumentFooter([
            'footer_title' => 'Clementine Stein',
            'show_footer_title' => true,
            'show_footer_message' => false,
            'show_address' => false,
            'show_phone' => false,
            'show_email' => false,
            'show_website' => false,
        ]);

        $this->blade('<x-document-footer :business="$business" :footer="$footer" />', compact('business', 'footer'))
            ->assertSee('Clementine Stein');
    }
}
