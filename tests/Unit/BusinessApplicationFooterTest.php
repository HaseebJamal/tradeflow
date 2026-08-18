<?php

namespace Tests\Unit;

use App\Services\BusinessDocumentFooterService;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BusinessApplicationFooterTest extends TestCase
{
    public function test_it_renders_only_the_minimal_business_application_footer(): void
    {
        $html = Blade::render('<x-business-application-footer :business="$business" />', [
            'business' => (object) [
                'business_name' => 'Gabimaru Limited',
                'address' => 'abc',
                'phone' => '+923456789452',
                'email' => 'gabimaru@example.test',
            ],
        ]);

        $this->assertStringContainsString('&copy; '.now()->year.' Gabimaru Limited', $html);
        $this->assertStringContainsString(app(BusinessDocumentFooterService::class)->platformPoweredByText(), $html);
        $this->assertStringNotContainsString('gabimaru@example.test', $html);
        $this->assertStringNotContainsString('+923456789452', $html);
        $this->assertStringNotContainsString('Thank you for your business!', $html);
    }
}
