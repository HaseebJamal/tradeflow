<?php

namespace Tests\Unit;

use App\Notifications\ActionableBusinessNotification;
use App\Services\BusinessNotificationPolicy;
use App\Services\OperationalAlertService;
use Carbon\Carbon;
use stdClass;
use Tests\TestCase;

class NotificationNoisePolicyTest extends TestCase
{
    public function test_only_real_delivery_failure_and_register_variance_are_actionable_activity(): void
    {
        $this->assertTrue(BusinessNotificationPolicy::isActionableActivity('Deliveries', 'Delivery marked failed'));
        $this->assertTrue(BusinessNotificationPolicy::isActionableActivity('POS', 'POS register closed', ['variance' => -2.50]));

        $this->assertFalse(BusinessNotificationPolicy::isActionableActivity('POS', 'POS register closed', ['variance' => 0]));
        $this->assertFalse(BusinessNotificationPolicy::isActionableActivity('POS', 'POS sale completed'));
        $this->assertFalse(BusinessNotificationPolicy::isActionableActivity('Products', 'Product updated'));
        $this->assertFalse(BusinessNotificationPolicy::isActionableActivity('Settings', 'Business profile updated'));
    }

    public function test_low_stock_and_expiry_conditions_are_classified_without_mutating_inventory(): void
    {
        $this->assertTrue(OperationalAlertService::isLowStock(5, 5));
        $this->assertTrue(OperationalAlertService::isLowStock(4.5, 5));
        $this->assertFalse(OperationalAlertService::isLowStock(6, 5));
        $this->assertFalse(OperationalAlertService::isLowStock(0, 0));

        $today = Carbon::parse('2026-08-16');
        $this->assertSame('expired', OperationalAlertService::batchExpiryStatus(Carbon::parse('2026-08-15'), 30, $today));
        $this->assertSame('expiring', OperationalAlertService::batchExpiryStatus(Carbon::parse('2026-09-15'), 30, $today));
        $this->assertSame('valid', OperationalAlertService::batchExpiryStatus(Carbon::parse('2026-09-16'), 30, $today));
    }

    public function test_actionable_notification_keeps_a_deduplication_key_and_context(): void
    {
        $notification = new ActionableBusinessNotification(
            'Low stock: Milk Powder',
            'Milk Powder is below reorder level.',
            10,
            'inventory',
            'warning',
            'low-stock:10:45',
            ['related_id' => 45],
        );

        $payload = $notification->toArray(new stdClass());

        $this->assertSame('low-stock:10:45', $payload['actionable_key']);
        $this->assertSame(10, $payload['business_id']);
        $this->assertSame('warning', $payload['priority']);
        $this->assertSame(45, $payload['related_id']);
    }
}
