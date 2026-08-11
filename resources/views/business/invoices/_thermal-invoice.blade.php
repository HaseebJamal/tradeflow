@php
    $amount = fn ($value) => 'Rs '.number_format((float) $value, 2);
    $invoiceItems = $invoice->items->isNotEmpty() ? $invoice->items : $order->items;
    $items = $invoiceItems->map(fn ($item) => [
        'name' => $item->product_name_snapshot ?: $item->product?->name,
        'meta' => $item->unit ?? $item->product?->unit,
        'quantity' => rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.').(($item->unit ?? null) ? ' '.$item->unit : ''),
        'rate' => $amount($item->unit_price ?? $item->price),
        'amount' => $amount($item->line_total ?? $item->total),
    ]);
    $totals = [
        ['label' => 'Subtotal', 'amount' => $amount($order->subtotal)],
        ['label' => 'Discount', 'amount' => $amount($order->discount_amount ?? 0), 'show' => (float) ($order->discount_amount ?? 0) !== 0.0],
        ['label' => 'Tax', 'amount' => $amount($order->tax_amount ?? 0), 'show' => (float) ($order->tax_amount ?? 0) !== 0.0],
        ['label' => 'Paid', 'amount' => $amount($invoice->paid_amount), 'show' => (float) $invoice->paid_amount > 0],
        ['label' => 'Due', 'amount' => $amount($invoice->balance), 'show' => (float) $invoice->balance > 0],
        ['label' => 'Grand total', 'amount' => $amount($order->grand_total ?: $order->total), 'emphasis' => true],
    ];
@endphp
<x-thermal-document
    :business="$order->business"
    :title="null"
    :number="$invoice->invoice_number"
    :date="($invoice->invoice_date ?: $invoice->created_at)?->format('n/j/Y, g:i A')"
    :cashier="$order->creator?->name"
    party-label="Customer"
    :party-name="$order->customer?->display_name ?? $order->customer?->name ?? 'Walk-in Customer'"
    :party-details="$order->customer?->address"
    :metadata="['Status' => $invoice->status, 'Payment status' => $invoice->payment_status]"
    :items="$items"
    :totals="$totals"
    :footer="$order->business?->documentFooter"
    :footer-lines="array_filter([$invoice->notes ?: null])"
    :paper="$paper ?? 80"
    :pdf="$pdf ?? false"
/>
