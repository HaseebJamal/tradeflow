<?php

namespace App\Services;

use App\Models\Product;

/**
 * Keeps product selling-price validation aligned anywhere products are edited.
 * Purchase cost is owned by accepted goods receipts, never by the product form.
 */
class ProductSellingPricePolicy
{
    /**
     * The same purchase-price precedence used by the product form.
     */
    public function purchasePrice(Product $product): float
    {
        return $product->currentPurchasePrice();
    }

    /**
     * @param  array<string, mixed>  $prices
     * @return array<string, string>
     */
    public function violations(array $prices, float $purchasePrice): array
    {
        $messages = [];

        foreach ([
            'retail_price' => 'Retail Selling Price',
            'wholesale_price' => 'Wholesale Selling Price',
        ] as $field => $label) {
            $value = $prices[$field] ?? 0;

            if (is_numeric($value) && (float) $value > $purchasePrice) {
                continue;
            }

            $messages[$field] = "{$label} must be greater than Purchase Price.";
        }

        return $messages;
    }
}
