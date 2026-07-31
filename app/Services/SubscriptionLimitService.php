<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
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
        // A pending subscription request is not an active entitlement and must
        // not block normal business operations while it awaits review.
        $subscription = Subscription::with('plan')
            ->where('business_id', $businessId)
            ->whereIn('status', ['Trial', 'Active', 'Expiring'])
            ->latest('starts_at')
            ->first();
        if (! $subscription?->plan) {
            return;
        }

        $limit = (int) $subscription->plan->{$field};
        if ($limit > 0 && $current + $incoming > $limit) {
            throw ValidationException::withMessages([
                'subscription' => 'Your current subscription plan limit has been reached. Please upgrade your plan.',
            ]);
        }
    }
}
