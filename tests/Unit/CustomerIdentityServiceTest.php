<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\CustomerIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerIdentityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Customer identity integration coverage requires the dedicated MySQL testing database.');
        }

        parent::setUp();
    }

    public function test_duplicate_phone_is_rejected_only_inside_the_same_business(): void
    {
        Customer::query()->create(['business_id' => 41, 'name' => 'Existing customer', 'phone' => '+923001112233', 'customer_type' => 'Retailer', 'status' => 'Active']);

        $service = app(CustomerIdentityService::class);
        try {
            // The NormalizePhoneNumbers middleware has already canonicalized
            // submitted contacts before this business service is reached.
            $service->assertAvailable(41, ['phone' => '+923001112233']);
            $this->fail('Expected duplicate phone to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }

        $service->assertAvailable(42, ['phone' => '+923001112233']);
    }

    public function test_duplicate_email_is_rejected_but_names_are_not_identifiers(): void
    {
        Customer::query()->create(['business_id' => 51, 'name' => 'Same name', 'email' => 'customer@example.test', 'customer_type' => 'Retailer', 'status' => 'Active']);
        $service = app(CustomerIdentityService::class);

        try {
            $service->assertAvailable(51, ['email' => ' CUSTOMER@example.test ']);
            $this->fail('Expected duplicate email to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $service->assertAvailable(51, ['name' => 'Same name']);
    }
}
