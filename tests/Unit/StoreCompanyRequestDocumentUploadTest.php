<?php

namespace Tests\Unit;

use App\Http\Requests\Admin\StoreCompanyRequest;
use Tests\TestCase;

class StoreCompanyRequestDocumentUploadTest extends TestCase
{
    public function test_company_creation_does_not_accept_verification_document_uploads(): void
    {
        $rules = (new StoreCompanyRequest)->rules();

        $this->assertArrayNotHasKey('cnic_image', $rules);
        $this->assertArrayNotHasKey('business_document', $rules);
        $this->assertArrayNotHasKey('shop_image', $rules);
        $this->assertArrayHasKey('company_logo', $rules);
    }
}
