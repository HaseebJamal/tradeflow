@extends('layouts.public')
@section('title', 'Features | TradeFlow')
@section('content')
<section class="tf-section" style="padding-top:140px">
    <div class="container">
        <h1 class="fw-bold mb-3">Features</h1>
        <p class="tf-muted mb-5">ERP-style frontend modules for wholesale operations.</p>
        <div class="row g-4">
            @foreach([
                ['Product Catalog','Maintain SKUs, units, pricing, and details.','bi-box'],
                ['Inventory Control','Review stock, low stock, and warehouse movement.','bi-clipboard-data'],
                ['Orders','Track retailer and wholesale order stages.','bi-bag-check'],
                ['Payments & Khata','Preview receipts, pending balances, and ledger rows.','bi-cash-stack'],
                ['Deliveries','Organize delivery status and assigned staff.','bi-truck'],
                ['Reports','Show revenue, expenses, profit, and product summaries.','bi-graph-up-arrow'],
            ] as [$title,$text,$icon])
            <div class="col-md-6 col-xl-4"><div class="tf-card p-4 h-100"><div class="tf-icon-tile bg-blue text-white mb-3"><i class="bi {{ $icon }}"></i></div><h2 class="h5">{{ $title }}</h2><p class="tf-muted mb-0">{{ $text }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
