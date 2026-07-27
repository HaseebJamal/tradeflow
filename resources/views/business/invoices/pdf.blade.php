<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body>
    @include('business.invoices._thermal-invoice', ['invoice' => $invoice, 'order' => $order, 'paper' => $paper ?? 80, 'pdf' => true])
</body>
</html>
