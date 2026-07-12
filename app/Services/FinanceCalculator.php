<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Collection;

class FinanceCalculator
{
    public function calculateOrderSubtotal(iterable $items): float
    {
        $total = collect($items)->sum(function ($item) {
            $quantity = (float) data_get($item, 'quantity', 0);
            $price = (float) data_get($item, 'price', 0);

            return $quantity * $price;
        });

        return round($total, 2);
    }

    public function calculateDiscountAmount(float $subtotal, float $discountPercentage): float
    {
        $percentage = max(0, min(100, $discountPercentage));

        return round($subtotal * ($percentage / 100), 2);
    }

    public function calculateGrandTotal(float $subtotal, float $discountAmount): float
    {
        return round(max(0, $subtotal - $discountAmount), 2);
    }

    public function calculatePaidAmount(Order $order): float
    {
        return round((float) $order->payments()->sum('amount'), 2);
    }

    public function calculateBalance(float $grandTotal, float $paidAmount): float
    {
        return round(max(0, $grandTotal - $paidAmount), 2);
    }

    public function calculateProfit(float $revenue, float $expenses): float
    {
        return round($revenue - $expenses, 2);
    }

    public function paymentStatus(float $grandTotal, float $paidAmount): string
    {
        if ($paidAmount >= $grandTotal && $grandTotal > 0) {
            return 'Paid';
        }

        if ($paidAmount > 0) {
            return 'Partial';
        }

        return 'Pending';
    }

    public function syncOrderTotals(Order $order): Order
    {
        $order->loadMissing('items', 'payments');
        $subtotal = $this->calculateOrderSubtotal($order->items);
        $discountPercentage = (float) ($order->discount_percentage ?? $order->discount ?? 0);
        $discountAmount = $discountPercentage > 0
            ? $this->calculateDiscountAmount($subtotal, $discountPercentage)
            : round((float) ($order->discount_amount ?? 0), 2);
        $taxAmount = round((float) ($order->tax_amount ?? 0), 2);
        $grandTotal = round($this->calculateGrandTotal($subtotal, $discountAmount) + $taxAmount, 2);
        $paidAmount = $this->calculatePaidAmount($order);
        $balance = $this->calculateBalance($grandTotal, $paidAmount);

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discountPercentage,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'total' => $grandTotal,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'balance' => $balance,
            'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
        ])->save();

        return $order->fresh();
    }

    public function syncOrderPaymentSummary(Order $order): Order
    {
        $grandTotal = (float) ($order->grand_total ?: $order->total);
        $paidAmount = $this->calculatePaidAmount($order);
        $balance = $this->calculateBalance($grandTotal, $paidAmount);

        $order->forceFill([
            'paid_amount' => $paidAmount,
            'balance' => $balance,
            'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
        ])->save();

        Invoice::where('order_id', $order->id)->update([
            'paid_amount' => $paidAmount,
            'balance' => $balance,
            'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
        ]);

        return $order->fresh();
    }

    public function orderAmountsFromLines(Collection $lines, float $discountPercentage): array
    {
        $subtotal = $this->calculateOrderSubtotal($lines);
        $discountAmount = $this->calculateDiscountAmount($subtotal, $discountPercentage);
        $grandTotal = $this->calculateGrandTotal($subtotal, $discountAmount);

        return compact('subtotal', 'discountAmount', 'grandTotal');
    }
}
