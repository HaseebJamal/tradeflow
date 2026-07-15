@extends('layouts.dashboard')
@section('page-title', 'Sale Saved')
@section('page-subtitle', 'Choose a receipt, invoice, or next action for this completed sale')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="tf-card p-4 p-lg-5 mb-4 text-center">
            <span class="tf-icon-tile bg-green text-white mb-3" style="width:58px;height:58px"><i class="bi bi-check2-circle fs-3"></i></span>
            <h1 class="h3 mb-1">Sale completed and saved</h1>
            <p class="tf-muted mb-4">Stock, payment, customer balance, accounting entries, and the invoice record have been updated.</p>
            <div class="row g-3 text-start">
                <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Sale number</small><strong>{{ $order->order_number }}</strong></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Customer</small><strong>{{ $order->customer?->display_name ?? 'Walk-in Customer' }}</strong></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Payment</small><strong>{{ $order->payment_status }} &middot; Rs {{ number_format($order->paid_amount, 2) }}</strong></div></div>
                <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Total</small><strong class="fs-5">Rs {{ number_format($order->grand_total, 2) }}</strong></div></div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="tf-card p-4 h-100">
                    <div class="d-flex gap-3"><span class="tf-icon-tile bg-blue text-white flex-shrink-0"><i class="bi bi-receipt"></i></span><div><h2 class="h5 mb-1">Receipt / Bill</h2><p class="tf-muted small mb-3">Provide a counter-sale bill with the completed payment and item details.</p>
                    @companyCan('pos.print_receipt')<div class="d-flex flex-wrap gap-2"><a href="{{ route('business.pos.receipt', $order) }}" class="btn btn-tf-primary"><i class="bi bi-eye me-1"></i>View receipt</a><a href="{{ route('business.pos.receipt.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Print / PDF</a><a href="{{ route('business.pos.receipt.pdf.download', $order) }}" class="btn btn-outline-secondary" title="Download receipt"><i class="bi bi-download"></i></a></div>@else<p class="small text-muted mb-0">You do not have receipt-printing permission.</p>@endcompanyCan
                    </div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="tf-card p-4 h-100">
                    <div class="d-flex gap-3"><span class="tf-icon-tile bg-navy text-white flex-shrink-0"><i class="bi bi-file-earmark-text"></i></span><div><h2 class="h5 mb-1">Tax Invoice</h2><p class="tf-muted small mb-3">Review, issue, print, or download the invoice linked to this saved sale.</p>
                    @companyCan('sales.invoices')<div class="d-flex flex-wrap gap-2"><a href="{{ route('business.sales.invoices.show', $order) }}" class="btn btn-tf-navy"><i class="bi bi-file-earmark-check me-1"></i>Generate invoice</a>@companyCan('sales.invoice_export')<a href="{{ route('business.sales.invoices.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Invoice PDF</a>@endcompanyCan</div>@else<p class="small text-muted mb-0">You do not have sales-invoice permission.</p>@endcompanyCan
                    </div></div>
                </div>
            </div>
        </div>

        <div class="tf-card p-3 p-lg-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h2 class="h5 mb-1">Manage this sale</h2><p class="tf-muted small mb-0">Continue working without losing the saved transaction.</p></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('business.pos.index') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>New sale</a><a href="{{ route('business.pos.history') }}" class="btn btn-outline-primary"><i class="bi bi-clock-history me-1"></i>Sales history</a>@companyCan('sales_returns.process')<a href="{{ route('business.pos.returns', $order) }}" class="btn btn-outline-warning"><i class="bi bi-arrow-return-left me-1"></i>Process return</a>@endcompanyCan</div></div>
        </div>
    </div>
</div>
@endsection
