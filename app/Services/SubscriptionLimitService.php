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
        $subscription = Subscription::with('plan')->where('business_id', $businessId)->first();
        if (! $subscription?->plan) {
            return;
        }

        if (! in_array($subscription->status, ['Trial', 'Active', 'Expiring'], true)) {
            throw ValidationException::withMessages([
                'subscription' => 'Your subscription is not active. Please contact your Platform Administrator.',
            ]);
        }

        $limit = (int) $subscription->plan->{$field};
        if ($limit > 0 && $current + $incoming > $limit) {
            throw ValidationException::withMessages([
                'subscription' => 'Your current subscription plan limit has been reached. Please upgrade your plan.',
            ]);
        }
    }
}
