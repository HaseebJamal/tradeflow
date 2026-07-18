<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\QueryException;

class BarcodeService
{
    public function valueFor(int $businessId, int $productId): string
    {
        return str_pad((string) $businessId, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $productId, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Product IDs make this numeric barcode stable and unique per business.
     */
    public function assign(Product $product): Product
    {
        if (filled($product->barcode)) {
            return $product;
        }

        $barcode = $this->valueFor((int) $product->business_id, (int) $product->id);
        $attempt = 0;
        while (Product::withTrashed()
            ->where('business_id', $product->business_id)
            ->where('barcode', $barcode)
            ->where('id', '!=', $product->id)
            ->exists()) {
            $attempt++;
            $barcode = substr($this->valueFor((int) $product->business_id, (int) $product->id), 0, 10)
                .str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
        }

        try {
            $product->update(['barcode' => $barcode]);
        } catch (QueryException $exception) {
            // A unique database index remains the final guard under concurrency.
            throw $exception;
        }

        return $product->fresh();
    }
}
