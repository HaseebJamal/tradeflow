<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Purchase {{ $purchase->purchase_number }}</title></head>
<body>
    @include('business.purchases._thermal-document', ['pdf' => true])
</body>
</html>
