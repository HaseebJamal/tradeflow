<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:DejaVu Sans,sans-serif;color:#111827}
        .invoice{padding:24px}
        .header{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;margin-bottom:20px}
        table{width:100%;border-collapse:collapse}
        th,td{border-bottom:1px solid #ddd;padding:8px;text-align:left}
        .total{text-align:right;margin-top:20px}
    </style>
</head>
<body>
<div class="invoice">
    <div class="header">
        <div><h1>TradeFlow Invoice</h1><p>{{ $order->business?->business_name }}</p></div>
        <div><h3>{{ $invoice->invoice_number }}</h3><p>{{ $invoice->created_at->format('M d, Y') }}</p></div>
    </div>
    <p><strong>Customer:</strong> {{ $order->customer?->business_name ?? $order->customer?->name }}</p>
    <table>
        <thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr><td>{{ $item->product?->name }}</td><td>{{ $item->quantity }}</td><td>Rs {{ number_format($item->price) }}</td><td>Rs {{ number_format($item->total) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="total">
        <p>Subtotal: Rs {{ number_format($order->subtotal) }}</p>
        <p>Discount: {{ number_format($order->discount_percentage ?? $order->discount ?? 0, 2) }}%</p>
        <p>Discount Amount: Rs {{ number_format($order->discount_amount ?? 0) }}</p>
        <p>Paid: Rs {{ number_format($invoice->paid_amount) }}</p>
        <p>Balance: Rs {{ number_format($invoice->balance) }}</p>
        <h2>Grand Total: Rs {{ number_format($order->grand_total ?: $order->total) }}</h2>
    </div>
</div>
</body>
</html>
