<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 5px; } .muted { color: #6b7280; }
        .logo { width: 56px; height: 56px; object-fit: contain; margin-bottom: 8px; }
        .header { text-align: center; border-bottom: 1px solid #d1d5db; padding-bottom: 12px; margin-bottom: 14px; }
        .meta { margin-bottom: 12px; } .meta-right { float: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 7px; text-align: left; }
        th:last-child, td:last-child { text-align: right; }
        .summary { width: 42%; margin-left: auto; } .summary td { border: 0; padding: 4px 0; } .summary td:last-child { text-align: right; }
        .total td { border-top: 1px solid #111827; font-weight: bold; padding-top: 8px; }
        .footer { border-top: 1px solid #e5e7eb; color: #6b7280; margin-top: 18px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
@php($receiptNumber = $order->invoice?->invoice_number ?? $order->order_number)
<div class="header">
    @if($order->business?->logo)<img class="logo" src="{{ public_path('storage/'.$order->business->logo) }}" alt="">@endif
    <h1>{{ $order->business?->business_name ?? $order->business?->name }}</h1>
    <div class="muted">Receipt {{ $receiptNumber }}</div>
    <div class="muted">{{ $order->order_date?->format('d M Y g:i A') }} | {{ $order->creator?->name }}</div>
</div>
<p class="meta"><strong>Customer:</strong> {{ $order->customer?->name ?? 'Walk-in Customer' }} <span class="meta-right"><strong>Payment Method:</strong> {{ $order->payment_method ?? $order->payment_type }}</span></p>
<table><thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->product_name_snapshot }}</td><td><x-quantity :value="$item->quantity" /></td><td>Rs {{ number_format($item->unit_price ?: $item->price) }}</td><td>{{ $item->discount_rate ?? 0 }}%</td><td>{{ $item->tax_rate ?? 0 }}%</td><td>Rs {{ number_format($item->line_total ?: $item->total) }}</td></tr>@endforeach</tbody></table>
<table class="summary"><tbody><tr><td>Subtotal</td><td>Rs {{ number_format($order->subtotal) }}</td></tr><tr><td>Discount</td><td>Rs {{ number_format($order->discount_amount) }}</td></tr><tr><td>Tax</td><td>Rs {{ number_format($order->tax_amount) }}</td></tr>@if($order->cash_received !== null)<tr><td>Cash Received</td><td>Rs {{ number_format($order->cash_received, 2) }}</td></tr>@endif<tr><td>Paid</td><td>Rs {{ number_format($order->paid_amount) }}</td></tr><tr><td>Due</td><td>Rs {{ number_format($order->balance) }}</td></tr><tr><td>Change</td><td>Rs {{ number_format($order->change_amount ?? 0) }}</td></tr><tr class="total"><td>Grand Total</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td></tr></tbody></table>
<div class="footer">Thank you for your business.</div>
</body>
</html>
