<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->invoice?->invoice_number ?? $order->order_number }}</title>
</head>
<body>
    @include('business.pos._thermal-receipt', ['order' => $order, 'paper' => $paper ?? 80, 'pdf' => true])
</body>
</html>
