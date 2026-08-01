@extends('layouts.dashboard')
@section('page-title', 'Purchase '.$purchase->purchase_number)
@section('page-subtitle', 'Supplier commitment, payments, and goods receiving')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><a href="{{ route('business.purchases.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Purchases</a><div class="d-flex gap-2"><span class="tf-badge {{ in_array($purchase->status, ['Confirmed','Received','Closed'], true) ? 'tf-badge-success' : ($purchase->status === 'Cancelled' ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $purchase->status }}</span><span class="tf-badge {{ $purchase->payment_status === 'Paid' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $purchase->payment_status }}</span><span class="tf-badge {{ $purchase->receiving_status === 'Fully Received' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $purchase->receiving_status ?? 'Not Received' }}</span></div></div>
<div class="row g-4"><div class="col-lg-8">
    <div class="tf-card p-4 mb-4"><div class="d-flex flex-wrap justify-content-between border-bottom pb-3 mb-3 gap-3"><div><h2 class="h5 mb-1">{{ $purchase->supplier?->supplier_name }}</h2><small class="d-block tf-muted">Supplier invoice: {{ $purchase->supplier_invoice_number ?: 'Not supplied' }}</small><small class="d-block tf-muted">Invoice date: {{ $purchase->supplier_invoice_date?->format('d M, Y') ?: 'Not supplied' }}</small></div><div class="text-lg-end"><strong>{{ $purchase->purchase_number }}</strong><small class="d-block tf-muted"><x-date-time :value="$purchase->purchase_date" /></small></div></div>
        <div class="row small mb-3"><div class="col-md-4"><strong>Supplier reference</strong><div>{{ $purchase->supplier_reference ?: 'Not supplied' }}</div></div><div class="col-md-4"><strong>Purchase order reference</strong><div>{{ $purchase->purchase_order_reference ?: 'Not supplied' }}</div></div><div class="col-md-4"><strong>Terms / due date</strong><div>{{ $purchase->payment_terms ?: 'Not supplied' }}{{ $purchase->due_date ? ' &middot; '.$purchase->due_date->format('d M, Y') : '' }}</div></div></div>
        <x-table><thead><tr><th>Product</th><th>Ordered</th><th>Unit</th><th>Received</th><th>Purchase cost</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead><tbody>@foreach($purchase->items as $item)<tr><td>{{ $item->product_name_snapshot }}</td><td><x-quantity :value="$item->quantity" /></td><td>{{ $item->unit_snapshot ?: ($item->product?->unit ?: 'Unit') }}</td><td><x-quantity :value="$item->received_quantity" /></td><td>Rs {{ number_format($item->unit_cost, 2) }}</td><td>{{ ($item->discount_type ?? 'fixed') === 'percentage' ? number_format($item->discount_value ?? 0). '%' : 'Rs '.number_format($item->discount_value ?? $item->discount_amount ?? 0, 2) }}</td><td>{{ ($item->tax_type ?? 'fixed') === 'percentage' ? number_format($item->tax_value ?? 0). '%' : 'Rs '.number_format($item->tax_value ?? $item->tax_amount ?? 0, 2) }}</td><td>Rs {{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody></x-table><div class="text-end mt-3"><div>Subtotal Rs {{ number_format($purchase->subtotal, 2) }}</div><div>Discount Rs {{ number_format($purchase->discount_amount, 2) }}</div><div>Tax Rs {{ number_format($purchase->tax_amount, 2) }}</div><div>Other charges Rs {{ number_format($purchase->other_charges, 2) }}</div><strong class="fs-5">Grand total Rs {{ number_format($purchase->grand_total, 2) }}</strong></div>
    </div>
    <div class="tf-card p-4 mb-4"><h2 class="h5">Payment history</h2><x-table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Recorded by</th><th>Amount</th></tr></thead><tbody>@forelse($purchase->payments as $payment)<tr><td>{{ $payment->payment_date?->format('d M, Y') }}</td><td>{{ $payment->method }}</td><td>{{ $payment->reference_number ?: 'Not provided' }}</td><td>{{ $payment->creator?->name ?? 'System' }}</td><td>Rs {{ number_format($payment->amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="text-center tf-muted">No payments recorded.</td></tr>@endforelse</tbody></x-table></div>
    <div class="tf-card p-4"><h2 class="h5">GRN history</h2><x-table><thead><tr><th>GRN</th><th>Date</th><th>Accepted</th><th>Damaged</th><th>Rejected</th><th>Recorded by</th></tr></thead><tbody>@forelse($purchase->goodsReceipts as $receipt)<tr><td><a href="{{ route('business.goods-receipts.show', $receipt) }}">{{ $receipt->grn_number }}</a></td><td><x-date-time :value="$receipt->received_at" /></td><td><x-quantity :value="$receipt->items->sum('accepted_quantity')" /></td><td><x-quantity :value="$receipt->items->sum('damaged_quantity')" /></td><td><x-quantity :value="$receipt->items->sum('rejected_quantity')" /></td><td>{{ $receipt->creator?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted">No goods receipts recorded.</td></tr>@endforelse</tbody></x-table></div>
</div><div class="col-lg-4">
    <div class="tf-card p-4 mb-4"><h2 class="h5">Purchase summary</h2><dl class="row mb-0"><dt class="col-7">Grand total</dt><dd class="col-5 text-end">Rs {{ number_format($purchase->grand_total, 2) }}</dd><dt class="col-7">Paid amount</dt><dd class="col-5 text-end">Rs {{ number_format($purchase->paid_amount, 2) }}</dd><dt class="col-7">Remaining payable</dt><dd class="col-5 text-end">Rs {{ number_format($purchase->balance, 2) }}</dd><dt class="col-7">Payment method</dt><dd class="col-5 text-end">{{ $purchase->payment_method ?: 'Not paid' }}</dd></dl></div>
    @if($purchase->status === 'Draft')<div class="tf-card p-4 mb-4"><h2 class="h5">Draft controls</h2>@companyCan('purchases.edit')<a href="{{ route('business.purchases.edit', $purchase) }}" class="btn btn-outline-primary w-100 mb-2">Edit Draft</a>@endcompanyCan @companyCan('purchases.cancel')<form method="POST" action="{{ route('business.purchases.cancel', $purchase) }}">@csrf<button class="btn btn-outline-danger w-100">Cancel Purchase</button></form>@endcompanyCan</div>@endif
    @if(in_array($purchase->status, ['Confirmed','Ordered'], true) && !in_array($purchase->receiving_status, ['Fully Received','Returned'], true))<div class="tf-card p-4 mb-4"><h2 class="h5">Goods receipt</h2><p class="tf-muted small">Record selected accepted, damaged, or rejected quantities. Selling prices are not changed.</p>@companyCan('purchases.receive')<a href="{{ route('business.purchases.receiving.create', $purchase) }}" class="btn btn-tf-primary w-100">Receive Goods</a>@endcompanyCan @companyCan('purchases.cancel')<form method="POST" action="{{ route('business.purchases.cancel', $purchase) }}" class="mt-2">@csrf<button class="btn btn-outline-danger w-100">Cancel Purchase</button></form>@endcompanyCan</div>@endif
    @if(in_array($purchase->status, ['Confirmed','Received','Ordered'], true) && $purchase->balance > 0)<div id="record-supplier-payment" class="tf-card p-4 mb-4"><h2 class="h5">Record supplier payment</h2>@companyCan('purchases.pay')<form method="POST" action="{{ route('business.purchases.pay', $purchase) }}" class="row g-2" data-supplier-payment-form data-payment-due="{{ (int) $purchase->balance }}">@csrf<div class="col-12"><label class="form-label">Payment Due</label><input class="form-control" type="text" value="Rs {{ number_format($purchase->balance) }}" readonly tabindex="-1"></div><div class="col-12"><label class="form-label" data-supplier-tender-label>Cash Given</label><input name="amount" type="text" inputmode="numeric" class="form-control" placeholder="Cash given" required autocomplete="off" data-supplier-tender></div><div class="col-12"><label class="form-label">Payment Method</label><select name="method" class="form-select" data-supplier-payment-method><option>Cash</option><option>Bank Transfer</option><option>JazzCash</option><option>Easypaisa</option><option>Cheque</option><option>Other</option></select></div><div class="col-12"><input name="reference_number" class="form-control" placeholder="Reference number"></div><div class="col-12"><input name="payment_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div><div class="col-12"><button class="btn btn-outline-primary w-100">Record Payment</button></div></form>@endcompanyCan</div>@endif
    <div class="tf-card p-4"><h2 class="h5">Activity</h2><dl class="small mb-0"><dt>Created by</dt><dd>{{ $purchase->creator?->name ?? 'System' }} &middot; {{ $purchase->created_at?->format('d M Y h:i A') }}</dd><dt>Updated by</dt><dd>{{ $purchase->updater?->name ?? 'Not updated' }} &middot; {{ $purchase->updated_at?->format('d M Y h:i A') }}</dd><dt>Confirmed by</dt><dd>{{ $purchase->confirmer?->name ?? 'Not confirmed' }}{{ $purchase->confirmed_at ? ' &middot; '.$purchase->confirmed_at->format('d M Y h:i A') : '' }}</dd></dl></div>
</div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-supplier-payment-form]');
    if (!form || form.dataset.cashTenderReady) return;
    form.dataset.cashTenderReady = '1';

    const normalizeMoney = value => String(value ?? '')
        .replace(/^\s*Rs\.?\s*/i, '')
        .replace(/[\s,]/g, '');
    const due = Number(normalizeMoney(form.dataset.paymentDue)) || 0;
    const tender = form.querySelector('[data-supplier-tender]');
    const method = form.querySelector('[data-supplier-payment-method]');
    const label = form.querySelector('[data-supplier-tender-label]');
    const isCash = () => method.value === 'Cash';

    const refresh = () => {
        const normalizedTender = normalizeMoney(tender.value);
        const tenderIsWhole = /^\d+$/.test(normalizedTender);
        const amount = tenderIsWhole ? Number(normalizedTender) : 0;
        const cash = isCash();
        label.textContent = cash ? 'Cash Given' : 'Payment Amount';
        tender.placeholder = cash ? 'Cash given' : 'Payment amount';
        tender.setCustomValidity(!tenderIsWhole
            ? 'Enter a whole-number payment amount.'
            : (amount > due ? 'Payment amount cannot exceed the remaining payable.' : ''));
    };

    tender.addEventListener('input', refresh);
    method.addEventListener('change', refresh);
    form.addEventListener('submit', event => {
        refresh();
        if (!tender.checkValidity()) {
            event.preventDefault();
            tender.reportValidity();
            return;
        }
        tender.value = normalizeMoney(tender.value);
    });
    refresh();
});
</script>
@endpush
@endsection
