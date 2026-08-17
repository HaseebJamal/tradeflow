<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Services\AuditDescriptionService;
use Tests\TestCase;

class AuditDescriptionServiceTest extends TestCase
{
    private AuditDescriptionService $descriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->descriptions = app(AuditDescriptionService::class);
    }

    public function test_product_sale_purchase_and_staff_events_use_business_language(): void
    {
        $product = new AuditLog([
            'user_name' => 'Steve', 'module' => 'Products', 'action' => 'product_created',
            'new_values' => ['name' => 'Coca-Cola 500ml'],
        ]);
        $sale = new AuditLog([
            'user_name' => 'Steve', 'module' => 'POS', 'action' => 'Completed POS sale INV-000123',
            'new_values' => ['grand_total' => 5400],
        ]);
        $purchase = new AuditLog([
            'user_name' => 'Steve', 'module' => 'Purchases', 'action' => 'Purchase order created: PINV-000021',
        ]);
        $staff = new AuditLog([
            'user_name' => 'Steve', 'module' => 'Staff', 'action' => 'staff viewed',
            'description' => 'Steve staff viewed for Ali Khan',
        ]);

        $this->assertSame('Steve created product Coca-Cola 500ml.', $this->descriptions->describe($product));
        $this->assertSame('Steve completed sale INV-000123 for Rs 5,400.', $this->descriptions->describe($sale));
        $this->assertSame('Steve created purchase PINV-000021.', $this->descriptions->describe($purchase));
        $this->assertSame("Steve viewed Ali Khan's staff profile.", $this->descriptions->describe($staff));
    }

    public function test_deleted_or_unknown_targets_keep_a_safe_fallback(): void
    {
        $log = new AuditLog([
            'user_name' => 'Steve', 'module' => 'Products', 'action' => 'sync_completed',
        ]);

        $this->assertSame('Steve updated a Products record.', $this->descriptions->describe($log));
    }

    public function test_old_and_new_values_are_labeled_and_sensitive_values_are_removed(): void
    {
        $changes = AuditDescriptionService::valueChanges(
            ['retail_price' => 150, 'password' => 'never-display', 'api_secret' => 'never-display'],
            ['retail_price' => 160, 'wholesale_price' => 140, 'remember_token' => 'never-display'],
        );

        $this->assertSame([
            ['label' => 'Retail Price', 'old' => 'Rs 150', 'new' => 'Rs 160'],
            ['label' => 'Wholesale Price', 'old' => '—', 'new' => 'Rs 140'],
        ], $changes);
    }

    public function test_balance_adjustments_are_recorded_in_business_language(): void
    {
        $customer = new AuditLog([
            'user_name' => 'Steve', 'module' => 'Customers', 'action' => 'customer_balance_adjusted',
            'new_values' => ['customer_name' => 'Duncan Benton', 'direction' => 'decreased', 'amount' => 2000, 'reason' => 'Reconciliation'],
        ]);
        $supplier = new AuditLog([
            'user_name' => 'Steve', 'module' => 'Suppliers', 'action' => 'supplier_balance_adjusted',
            'new_values' => ['supplier_name' => 'ABC Traders', 'direction' => 'increased', 'amount' => 5000, 'reason' => 'Opening Balance Correction'],
        ]);

        $this->assertSame("Steve decreased Duncan Benton's receivable by Rs 2,000 (Reconciliation).", $this->descriptions->describe($customer));
        $this->assertSame("Steve increased ABC Traders' payable by Rs 5,000 (Opening Balance Correction).", $this->descriptions->describe($supplier));
    }
}
