<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class SubscriptionLimitService
{
    public function assertCanCreateProducts(int $businessId, int $incoming = 1): void
    {
        $this->assertWithinLimit($businessId, 'product_limit', Product::where('business_id', $businessId)->count(), $incoming, 'products');
    }

    public function assertCanCreateOrder(int $businessId): void
    {
        $this->assertWithinLimit($businessId, 'order_limit', Order::where('business_id', $businessId)->count(), 1, 'orders');
    }

    private function assertWithinLimit(int $businessId, string $field, int $current, int $incoming, string $label): void
    {
        $business = \App\Models\Business::with('subscription.plan')->find($businessId);
        $state = $business ? app(SubscriptionLifecycleService::class)->forBusiness($business) : null;
        if ($state && ! $state['can_access_business']) {
            throw ValidationException::withMessages([
                'subscription' => 'Your access period has ended. Contact Profit Point to continue.',
            ]);
        }

        // A pending subscription request is not an active entitlement and must
        // not block normal business operations while it awaits review.
        $subscription = $state['subscription'] ?? null;
        // Profit Point access is negotiated per business. Historical plans
        // are retained as records, but must not impose product, order, or
        // staff limits on a current trial or paid access period.
        return;
    }
}
