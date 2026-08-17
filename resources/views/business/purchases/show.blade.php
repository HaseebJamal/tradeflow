@extends('layouts.dashboard')
@section('page-title', 'Purchase '.$purchase->purchase_number)
@section('page-subtitle', 'Supplier commitment, payments, and goods receiving')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php($totalPayableAdjustments = $paymentSummary['receipt_adjustments'] + $paymentSummary['return_adjustments'])
@php($refundBadgeClass = match($refundSummary['status'] ?? null) { 'Refunded / Fully Adjusted' => 'tf-badge-success', 'Partially Refunded' => 'tf-badge-primary', default => 'tf-badge-warning' })
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><a href="{{ route('business.purchases.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Purchases</a><div class="d-flex flex-wrap gap-2"><span class="tf-badge {{ in_array($purchase->status, ['Confirmed','Received','Closed'], true) ? 'tf-badge-success' : ($purchase->status === 'Cancelled' ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $purchase->status }}</span><span class="tf-badge {{ $paymentSummary['payment_status'] === 'Paid' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $paymentSummary['payment_status'] }}</span><span class="tf-badge {{ $receiptState['receipt_status'] === 'Fully Received' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $receiptState['receipt_status'] }}</span>@if($refundSummary['status'] ?? null)<span class="tf-badge {{ $refundBadgeClass }}">{{ $refundSummary['status'] }}</span>@endif</div></div>
<div class="row g-4"><div class="col-lg-8">
    <div class="tf-card p-4 mb-4"><div class="d-flex flex-wrap justify-content-between border-bottom pb-3 mb-3 gap-3"><div><h2 class="h5 mb-1">{{ $purchase->supplier?->supplier_name }}</h2><small class="d-block tf-muted">Supplier invoice: {{ $purchase->supplier_invoice_number ?: 'Not supplied' }}</small><small class="d-block tf-muted">Invoice date: {{ $purchase->supplier_invoice_date?->format('n/j/Y') ?: 'Not supplied' }}</small></div><div class="text-lg-end"><strong>{{ $purchase->purchase_number }}</strong><small class="d-block tf-muted"><x-date-time :value="$purchase->purchase_date" /></small></div></div>
        <div class="row small mb-3"><div class="col-md-4"><strong>Supplier reference</strong><div>{{ $purchase->supplier_reference ?: 'Not supplied' }}</div></div><div class="col-md-4"><strong>Purchase order reference</strong><div>{{ $purchase->purchase_order_reference ?: 'Not supplied' }}</div></div><div class="col-md-4"><strong>Terms / due date</strong><div>{{ $purchase->payment_terms ?: 'Not supplied' }}@if($purchase->due_date) &middot; {{ $purchase->due_date->format('n/j/Y') }}@endif</div></div></div>
        <x-table><thead><tr><th>Product</th><th>Purchased</th><th>Free</th><th>Total expected</th><th>Unit</th><th>Accepted</th><th>Damaged</th><th>Rejected</th><th>Purchase cost</th><th>Total</th></tr></thead><tbody>@foreach($purchase->items as $item)<tr><td>{{ $item->product_name_snapshot }}</td><td><x-quantity :value="$item->quantity" /></td><td><x-quantity :value="$item->free_quantity ?? 0" /></td><td><x-quantity :value="(float) $item->quantity + (float) ($item->free_quantity ?? 0)" /></td><td>{{ $item->unit_snapshot ?: ($item->product?->unit ?: 'Unit') }}</td><td><x-quantity :value="$item->received_quantity" /></td><td><x-quantity :value="$item->damaged_quantity" /></td><td><x-quantity :value="$item->rejected_quantity" /></td><td>Rs {{ number_format($item->unit_cost, 2) }}</td><td>Rs {{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody></x-table><div class="text-end mt-3"><div>Subtotal Rs {{ number_format($purchase->subtotal, 2) }}</div><div>Discount Rs {{ number_format($purchase->discount_amount, 2) }}</div><div>Tax Rs {{ number_format($purchase->tax_amount, 2) }}</div><div>Other charges Rs {{ number_format($purchase->other_charges, 2) }}</div><strong class="fs-5">Grand total Rs {{ number_format($purchase->grand_total, 2) }}</strong></div>
    </div>
    <div class="tf-card p-4 mb-4"><h2 class="h5">Payment history</h2><x-table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Recorded by</th><th>Amount</th></tr></thead><tbody>@forelse($purchase->payments as $payment)<tr><td>{{ $payment->payment_date?->format('n/j/Y') }}</td><td>{{ $payment->method }}</td><td>{{ $payment->reference_number ?: 'Not provided' }}</td><td>{{ $payment->creator?->name ?? 'System' }}</td><td>Rs {{ number_format($payment->amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="text-center tf-muted">No payments recorded.</td></tr>@endforelse</tbody></x-table></div>
    <div class="tf-card p-4"><h2 class="h5">GRN history</h2><x-table><thead><tr><th>GRN</th><th>Date</th><th>Accepted</th><th>Damaged</th><th>Rejected</th><th>Recorded by</th></tr></thead><tbody>@forelse($purchase->goodsReceipts as $receipt)<tr><td><a href="{{ route('business.goods-receipts.show', $receipt) }}">{{ $receipt->grn_number }}</a></td><td><x-date-time :value="$receipt->received_at" /></td><td><x-quantity :value="$receipt->items->sum('accepted_quantity')" /></td><td><x-quantity :value="$receipt->items->sum('damaged_quantity')" /></td><td><x-quantity :value="$receipt->items->sum('rejected_quantity')" /></td><td>{{ $receipt->creator?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted">No goods receipts recorded.</td></tr>@endforelse</tbody></x-table></div>
</div><div class="col-lg-4">
    <div class="tf-card p-4 mb-4"><h2 class="h5">Purchase summary</h2><dl class="row mb-0"><dt class="col-7">Grand total</dt><dd class="col-5 text-end">Rs {{ number_format($paymentSummary['gross_total'], 2) }}</dd>@if($totalPayableAdjustments > 0)<dt class="col-7">Receipt / return adjustments</dt><dd class="col-5 text-end">- Rs {{ number_format($totalPayableAdjustments, 2) }}</dd><dt class="col-7">Payable total</dt><dd class="col-5 text-end">Rs {{ number_format($paymentSummary['net_liability'], 2) }}</dd>@endif<dt class="col-7">Paid amount</dt><dd class="col-5 text-end">Rs {{ number_format($paymentSummary['paid_amount'], 2) }}</dd><dt class="col-7">Remaining payable</dt><dd class="col-5 text-end">Rs {{ number_format($paymentSummary['balance'], 2) }}</dd><dt class="col-7">Payment method</dt><dd class="col-5 text-end">{{ $purchase->payment_method ?: $purchase->latestPayment?->method ?: ((float) $paymentSummary['paid_amount'] > 0 ? 'Payment recorded' : 'Not paid') }}</dd></dl></div>
    @if($refundSummary['status'] ?? null)
        <div class="tf-card p-4 mb-4">
            <h2 class="h5">Refund / Credit summary</h2>
            <dl class="row mb-0">
                <dt class="col-7">Rejected / damaged value</dt><dd class="col-5 text-end">Rs {{ number_format($refundSummary['recoverable_amount'], 2) }}</dd>
                <dt class="col-7">Credited against payable</dt><dd class="col-5 text-end">Rs {{ number_format($refundSummary['credited_amount'], 2) }}</dd>
                <dt class="col-7">Refunded / credited</dt><dd class="col-5 text-end">Rs {{ number_format($refundSummary['refunded_amount'], 2) }}</dd>
                <dt class="col-7">Refund remaining</dt><dd class="col-5 text-end">Rs {{ number_format($refundSummary['remaining_amount'], 2) }}</dd>
                <dt class="col-7">Refund status</dt><dd class="col-5 text-end"><span class="tf-badge {{ $refundBadgeClass }}">{{ $refundSummary['status'] }}</span></dd>
            </dl>
        </div>
    @endif
    @if(($refundSummary['remaining_amount'] ?? 0) > 0.009)
        @companyCan('purchase_returns.process')
            <div class="tf-card p-4 mb-4">
                <h2 class="h5">Record supplier refund</h2>
                <p class="tf-muted small">Record money actually returned by the supplier. Payable credits already posted from the GRN are shown above and are not duplicated here.</p>
                <form method="POST" action="{{ route('business.purchases.refund-settlements.store', $purchase) }}" class="row g-2" data-tf-submit-once>
                    @csrf
                    <div class="col-12"><label class="form-label">Refund remaining</label><input class="form-control" value="Rs {{ number_format($refundSummary['remaining_amount'], 2) }}" readonly></div>
                    <div class="col-12"><label class="form-label">Amount</label><input name="amount" type="number" min="0.01" max="{{ $refundSummary['remaining_amount'] }}" step="0.01" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Method</label><select name="method" class="form-select"><option>Cash</option><option>Bank Transfer</option><option>Jazz Cash</option><option>Easypaisa</option><option>Cheque</option><option>Other</option></select></div>
                    <div class="col-12"><label class="form-label">Reference</label><input name="reference_number" class="form-control" maxlength="120"></div>
                    <div class="col-12"><label class="form-label">Settlement date</label><input name="settled_at" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="1000"></div>
                    <div class="col-12"><button class="btn btn-outline-primary w-100">Record Refund</button></div>
                </form>
            </div>
        @endcompanyCan
    @endif
    @if($purchase->status === 'Draft')<div class="tf-card p-4 mb-4"><h2 class="h5">Draft controls</h2>@companyCan('purchases.edit')<a href="{{ route('business.purchases.edit', $purchase) }}" class="btn btn-outline-primary w-100 mb-2">Edit Draft</a>@endcompanyCan @companyCan('purchases.cancel')<form method="POST" action="{{ route('business.purchases.cancel', $purchase) }}">@csrf<button class="btn btn-outline-danger w-100">Cancel Purchase</button></form>@endcompanyCan</div>@endif
    @if($receiptState['can_receive'])<div class="tf-card p-4 mb-4"><h2 class="h5">Goods receipt</h2><p class="tf-muted small">Record selected accepted, damaged, or rejected quantities. Selling prices are not changed.</p>@companyCan('purchases.receive')<a href="{{ route('business.purchases.receiving.create', $purchase) }}" class="btn btn-tf-primary w-100">{{ $receiptState['action_label'] }}</a>@endcompanyCan @companyCan('purchases.cancel')<form method="POST" action="{{ route('business.purchases.cancel', $purchase) }}" class="mt-2">@csrf<button class="btn btn-outline-danger w-100">Cancel Purchase</button></form>@endcompanyCan</div>@endif
    @if(in_array($purchase->status, ['Confirmed','Received','Ordered'], true) && $paymentSummary['balance'] > 0)<div id="record-supplier-payment" class="tf-card p-4 mb-4"><h2 class="h5">Record supplier payment</h2>@companyCan('purchases.pay')<form method="POST" action="{{ route('business.purchases.pay', $purchase) }}" class="row g-2" data-supplier-payment-form data-payment-due="{{ (int) $paymentSummary['balance'] }}">@csrf<div class="col-12"><label class="form-label">Payment Due</label><input class="form-control" type="text" value="Rs {{ number_format($paymentSummary['balance']) }}" readonly tabindex="-1"></div><div class="col-12"><label class="form-label" data-supplier-tender-label>Cash Given</label><input name="amount" type="text" inputmode="numeric" class="form-control" placeholder="Cash given" required autocomplete="off" data-supplier-tender></div><div class="col-12"><label class="form-label">Payment Method</label><select name="method" class="form-select" data-supplier-payment-method><option>Cash</option><option>Bank Transfer</option><option>Jazz Cash</option><option>Easypaisa</option><option>Cheque</option><option>Other</option></select></div><div class="col-12"><input name="reference_number" class="form-control" placeholder="Reference number"></div><div class="col-12"><input name="payment_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div><div class="col-12"><button class="btn btn-outline-primary w-100">Record Payment</button></div></form>@endcompanyCan</div>@endif
    <div class="tf-card p-4"><h2 class="h5">Activity</h2><dl class="small mb-0"><dt>Created by</dt><dd>{{ $purchase->creator?->name ?? 'System' }} &middot; {{ $purchase->created_at?->format('n/j/Y, g:i A') }}</dd><dt>Updated by</dt><dd>{{ $purchase->updater?->name ?? 'Not updated' }} &middot; {{ $purchase->updated_at?->format('n/j/Y, g:i A') }}</dd><dt>Confirmed by</dt><dd>{{ $purchase->confirmer?->name ?? 'Not confirmed' }}{{ $purchase->confirmed_at ? ' &middot; '.$purchase->confirmed_at->format('n/j/Y, g:i A') : '' }}</dd></dl></div>
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
