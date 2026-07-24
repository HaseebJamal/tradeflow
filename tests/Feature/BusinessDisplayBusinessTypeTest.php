<?php

namespace Tests\Feature;

use App\Models\Business;
use Tests\TestCase;

class BusinessDisplayBusinessTypeTest extends TestCase
{
    public function test_other_business_type_uses_the_saved_custom_description(): void
    {
        $business = new Business([
            'business_type' => 'Other',
            'business_description' => 'Pharmacy',
        ]);

        $this->assertSame('Pharmacy', $business->display_business_type);
    }

    public function test_standard_business_types_remain_unchanged(): void
    {
        $business = new Business([
            'business_type' => 'Manufacturer',
            'business_description' => 'Ignored description',
        ]);

        $this->assertSame('Manufacturer', $business->display_business_type);
    }
}
