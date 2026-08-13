@extends('layouts.dashboard')
@section('page-title', 'Purchases')
@section('page-subtitle', 'Purchase orders, goods received, supplier invoices, payments, and returns')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div><h2 class="h5 mb-1">All Purchases</h2><p class="tf-muted mb-0">Track supplier commitments through receiving, payment, and return.</p></div>
    <div>
        @if(! $showPurchaseCreate)
        @companyCan('purchases.create')
            <a href="{{ route('business.purchases.index', ['create' => 1]) }}#purchase-create" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>New Purchase</a>
        @endcompanyCan
        @endif
    </div>
</div>

@if($showPurchaseCreate)
<section id="purchase-create" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Create Purchase</h2><a href="{{ route('business.purchases.index') }}" class="btn btn-sm btn-outline-secondary">Close</a></div>
    @include('business.purchases._form')
</section>
@endif

<form class="tf-card tf-purchase-filter-card p-3 mb-3 row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Purchase / Invoice</label><select name="purchase_id" class="form-select" data-placeholder="All Purchases" autofocus><option value="">All Purchases</option>@foreach($purchaseOptions as $option)<option value="{{ $option->id }}" @selected((int) request('purchase_id') === $option->id)>{{ $option->purchase_number }}{{ $option->supplier_invoice_number ? ' · ' . $option->supplier_invoice_number : '' }}{{ $option->supplier?->supplier_name ? ' · ' . $option->supplier->supplier_name : '' }}</option>@endforeach</select></div>
    @if($canViewSuppliers)
        <div class="col-md-2"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
    @endif
    <div class="col-md-2"><label class="form-label">Payment Status</label><select name="payment_status" class="form-select"><option value="">All payment statuses</option>@foreach($paymentStatuses as $paymentStatus)<option value="{{ $paymentStatus }}" @selected(request('payment_status') === $paymentStatus)>{{ $paymentStatus }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Created By</label><select name="created_by" class="form-select"><option value="">All users</option>@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(request('created_by') == $creator->id)>{{ $creator->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Purchase Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Draft','Confirmed','Received','Cancelled','Closed','Ordered','Partially Returned','Returned'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
    <div class="col-md-1 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('business.purchases.index', ['clear' => 1]) }}" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
</form>

<x-table class="tf-business-data-table tf-purchase-data-table"><thead><tr><th>Purchase</th><th>Supplier</th><th>Amount</th><th>Paid / Due</th><th>Payment Status</th><th>Purchase Date</th><th>Created By</th><th>Actions</th></tr></thead><tbody>@forelse($purchases as $purchase)
    @php
        $receivedQuantity = (float) $purchase->items->sum('received_quantity');
        $returnedQuantity = (float) $purchase->returns->flatMap(fn ($return) => $return->items)->sum('quantity');
        $paymentMethod = $purchase->payment_method ?: $purchase->latestPayment?->method;
        $paymentMethodLabel = $paymentMethod ?: ((float) $purchase->paid_amount > 0 ? 'Payment recorded' : 'Not paid');
        $receiptState = $purchase->receipt_state;
        $canReceive = $receiptState['can_receive'];
        $canPay = in_array($purchase->status, ['Confirmed', 'Received', 'Ordered'], true) && (float) $purchase->balance > 0;
        $canReturn = $receivedQuantity > $returnedQuantity;
        $canCancel = in_array($purchase->status, ['Draft', 'Confirmed'], true) && ! $purchase->received_at;
        $canEdit = in_array($purchase->status, ['Draft', 'Confirmed'], true) && ! $purchase->received_at && (int) $purchase->payments_count === 0;
    @endphp
    <tr><td><strong>{{ $purchase->purchase_number }}</strong><small class="d-block tf-muted">{{ $purchase->supplier_invoice_number ?: 'No supplier invoice' }} · <x-quantity :value="$purchase->items_sum_quantity" /> units</small><small class="d-block mt-1"><span class="tf-badge {{ in_array($purchase->status, ['Confirmed','Received','Closed'], true) ? 'tf-badge-success' : ($purchase->status === 'Cancelled' ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $purchase->status }}</span></small></td><td>{{ $purchase->supplier?->supplier_name }}</td><td>Rs {{ number_format($purchase->grand_total, 2) }}</td><td>Rs {{ number_format($purchase->paid_amount, 2) }}<small class="d-block tf-muted">Due Rs {{ number_format($purchase->balance, 2) }}</small></td><td>{{ $paymentMethodLabel }}<small class="d-block mt-1"><span class="tf-badge {{ $purchase->payment_status === 'Paid' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $purchase->payment_status }}</span></small></td><td><x-date-time :value="$purchase->purchase_date" /></td><td>{{ $purchase->creator?->name ?? 'System' }}</td><td>
        <div class="d-flex justify-content-end align-items-center gap-1">
            <button class="btn btn-sm btn-outline-primary tf-table-view-action" type="button" data-bs-toggle="modal" data-bs-target="#purchaseDetailsModal{{ $purchase->id }}">View</button>
            <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary tf-table-more-action" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-display="dynamic" aria-expanded="false" aria-label="More actions for {{ $purchase->purchase_number }}"><i class="bi bi-three-dots"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                @companyCan('purchases.edit')
                    @if($canEdit)
                        <li><a class="dropdown-item" href="{{ route('business.purchases.edit', $purchase) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                    @endif
                @endcompanyCan
                @companyCan('purchases.receive')
                    @if($canReceive)
                        <li><a class="dropdown-item" href="{{ route('business.purchases.receiving.create', $purchase) }}"><i class="bi bi-box-arrow-in-down me-2"></i>{{ $receiptState['action_label'] }}</a></li>
                    @endif
                @endcompanyCan
                @companyCan('purchases.pay')
                    @if($canPay)
                        <li><a class="dropdown-item" href="{{ route('business.purchases.show', $purchase) }}#record-supplier-payment"><i class="bi bi-cash-stack me-2"></i>Record Payment</a></li>
                    @endif
                @endcompanyCan
                @companyCan('purchase_returns.process')
                    @if($canReturn)
                        <li><a class="dropdown-item" href="{{ route('business.purchase-returns.create', ['purchase_id' => $purchase->id]) }}"><i class="bi bi-arrow-return-left me-2"></i>Returns</a></li>
                    @endif
                @endcompanyCan
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('business.purchases.show', ['purchase' => $purchase, 'document' => 'print']) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-2"></i>Print</a></li>
                <li><a class="dropdown-item" href="{{ route('business.purchases.show', ['purchase' => $purchase, 'document' => 'pdf']) }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</a></li>
                @companyCan('purchases.cancel')
                    @if($canCancel)
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="POST" action="{{ route('business.purchases.cancel', $purchase) }}" data-tf-confirm-message="Cancel this purchase? This cannot be undone.">@csrf<button class="dropdown-item text-danger" type="submit"><i class="bi bi-x-circle me-2"></i>Cancel Purchase</button></form></li>
                    @endif
                @endcompanyCan
            </ul>
            </div>
        </div>
    </td></tr>
@empty<tr><td colspan="8" class="text-center tf-muted py-5">No purchases found.</td></tr>@endforelse</tbody></x-table>

@foreach($purchases as $purchase)
    @php
        $modalPaymentMethod = $purchase->payment_method ?: $purchase->latestPayment?->method;
        $modalPaymentMethodLabel = $modalPaymentMethod ?: ((float) $purchase->paid_amount > 0 ? 'Payment recorded' : 'Not paid');
    @endphp
    <div class="modal fade" id="purchaseDetailsModal{{ $purchase->id }}" tabindex="-1" aria-labelledby="purchaseDetailsModalLabel{{ $purchase->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable tf-purchase-preview-modal">
            <div class="modal-content">
                <div class="modal-header py-3">
                    <div>
                        <h2 class="modal-title fs-5 mb-0" id="purchaseDetailsModalLabel{{ $purchase->id }}">Purchase Details</h2>
                        <small class="text-muted">{{ $purchase->purchase_number }}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="tf-purchase-preview-details">
                        <div><span>Purchase / Invoice</span><strong>{{ $purchase->purchase_number }}{{ $purchase->supplier_invoice_number ? ' · '.$purchase->supplier_invoice_number : '' }}</strong></div><div><span>Supplier</span><strong>{{ $purchase->supplier?->supplier_name ?? 'Not provided' }}</strong></div>
                        <div><span>Purchase date &amp; time</span><strong><x-date-time :value="$purchase->purchase_date" /></strong></div><div><span>Created by</span><strong>{{ $purchase->creator?->name ?? 'System' }}</strong></div>
                        <div><span>Purchase status</span><strong><span class="tf-badge {{ in_array($purchase->status, ['Confirmed','Received','Closed'], true) ? 'tf-badge-success' : ($purchase->status === 'Cancelled' ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $purchase->status }}</span></strong></div><div><span>Payment status</span><strong><span class="tf-badge {{ $purchase->payment_status === 'Paid' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $purchase->payment_status }}</span></strong></div>
                        <div><span>Grand total</span><strong>Rs {{ number_format($purchase->grand_total, 2) }}</strong></div><div><span>Paid / remaining due</span><strong>Rs {{ number_format($purchase->paid_amount, 2) }} / Rs {{ number_format($purchase->balance, 2) }}</strong></div>
                        <div><span>Payment method</span><strong>{{ $modalPaymentMethodLabel }}</strong></div><div><span>Receiving</span><strong>{{ $purchase->receipt_state['receipt_status'] }}</strong></div>
                    </div>
                    <section class="tf-purchase-preview-section"><h3>Item summary</h3><div class="tf-purchase-preview-items">@foreach($purchase->items as $item)<div><span><strong>{{ $item->product_name_snapshot ?: 'Product' }}</strong><small>Ordered {{ rtrim(rtrim(number_format($item->quantity, 3, '.', ''), '0'), '.') }}{{ $item->unit_snapshot ? ' '.$item->unit_snapshot : '' }} · Rs {{ number_format($item->unit_cost, 2) }}</small></span><b>Rs {{ number_format($item->line_total, 2) }}</b><small>Accepted {{ rtrim(rtrim(number_format($item->received_quantity, 3, '.', ''), '0'), '.') }} · Damaged {{ rtrim(rtrim(number_format($item->damaged_quantity, 3, '.', ''), '0'), '.') }} · Rejected {{ rtrim(rtrim(number_format($item->rejected_quantity, 3, '.', ''), '0'), '.') }}</small></div>@endforeach</div></section>
                    <section class="tf-purchase-preview-section"><h3>GRN history</h3>@forelse($purchase->goodsReceipts as $receipt)<div class="tf-purchase-preview-grn"><span><strong>{{ $receipt->grn_number }}</strong><small><x-date-time :value="$receipt->received_at" /> · {{ $receipt->creator?->name ?? 'System' }}</small></span><small>Accepted {{ rtrim(rtrim(number_format($receipt->items->sum('accepted_quantity'), 3, '.', ''), '0'), '.') }} · Damaged {{ rtrim(rtrim(number_format($receipt->items->sum('damaged_quantity'), 3, '.', ''), '0'), '.') }} · Rejected {{ rtrim(rtrim(number_format($receipt->items->sum('rejected_quantity'), 3, '.', ''), '0'), '.') }}</small></div>@empty<div class="tf-muted small">No goods receipts recorded.</div>@endforelse</section>
                </div>
                <div class="modal-footer py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="{{ route('business.purchases.show', $purchase) }}" class="btn btn-tf-primary">Open Full Purchase</a>
                </div>
            </div>
        </div>
    </div>
@endforeach

<div class="mt-3"><x-table-result-summary :paginator="$purchases" />{{ $purchases->links('pagination::bootstrap-5') }}</div>
@endsection
