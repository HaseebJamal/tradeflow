<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Purchase {{ $purchase->purchase_number }}</title></head>
<body onload="window.print()">
    @include('business.purchases._thermal-document', ['pdf' => false])
</body>
</html>
