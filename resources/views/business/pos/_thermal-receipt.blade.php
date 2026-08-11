@php
    $receiptNumber = $order->invoice?->invoice_number ?? $order->order_number;
    $amount = fn ($value) => 'Rs '.number_format((float) $value, 2);
    $items = $order->items->map(fn ($item) => [
        'name' => $item->product_name_snapshot ?: $item->product?->name,
        'meta' => $item->unit ?: $item->product?->unit,
        'quantity' => rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.').($item->unit ? ' '.$item->unit : ''),
        'rate' => $amount($item->unit_price ?: $item->price),
        'amount' => $amount($item->line_total ?: $item->total),
    ]);
    $totals = [
        ['label' => 'Subtotal', 'amount' => $amount($order->subtotal)],
        ['label' => 'Discount', 'amount' => $amount($order->discount_amount), 'show' => (float) $order->discount_amount !== 0.0],
        ['label' => 'Tax', 'amount' => $amount($order->tax_amount), 'show' => (float) $order->tax_amount !== 0.0],
        ['label' => 'Cash received', 'amount' => $amount($order->cash_received), 'show' => $order->cash_received !== null],
        ['label' => 'Paid', 'amount' => $amount($order->paid_amount), 'show' => (float) $order->paid_amount > 0],
        ['label' => 'Due', 'amount' => $amount($order->balance), 'show' => (float) $order->balance > 0],
        ['label' => 'Change', 'amount' => $amount($order->change_amount), 'show' => (float) ($order->change_amount ?? 0) > 0],
        ['label' => 'Grand total', 'amount' => $amount($order->grand_total ?: $order->total), 'emphasis' => true],
    ];
@endphp
<x-thermal-document
    :business="$order->business"
    :title="null"
    :number="$receiptNumber"
    :date="$order->order_date?->format('n/j/Y, g:i A')"
    :cashier="$order->creator?->name"
    party-label="Customer"
    :party-name="$order->customer?->display_name ?? $order->customer?->name ?? 'Walk-in Customer'"
    :metadata="['Payment method' => $order->payment_method ?? $order->payment_type]"
    :items="$items"
    :totals="$totals"
    :footer="$order->business?->documentFooter"
    :paper="$paper ?? 80"
    :pdf="$pdf ?? false"
/>
