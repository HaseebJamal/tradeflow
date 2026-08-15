<?php

namespace App\Services;

/**
 * Keeps retired notification-only alerts out of every inbox surface while
 * preserving the original database records for audit history.
 */
class NotificationVisibilityService
{
    private const INLINE_PRODUCT_PRICING_MESSAGE = 'Product pricing needs attention after a purchase cost update.';

    /** @param mixed $query */
    public function withoutInlineProductPricingAlert($query)
    {
        return $query->where(function ($query): void {
            $query->whereNull('data->message')
                ->orWhere('data->message', '!=', self::INLINE_PRODUCT_PRICING_MESSAGE);
        });
    }
}
