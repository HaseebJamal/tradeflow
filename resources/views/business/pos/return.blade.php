@extends('layouts.dashboard')
@section('page-title', 'POS Return')
@section('page-subtitle', $order->order_number)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route(request()->routeIs('business.sales.returns.*') ? 'business.sales.returns.store' : 'business.pos.returns.store', $order) }}" class="tf-card p-4" data-pos-return-form>
    @csrf
    <section class="border rounded bg-light p-3 mb-3" aria-label="Add return item">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><h2 class="h6 mb-0">Return item</h2><small class="tf-muted">Add one sold product at a time. The original sale and refund calculations stay unchanged.</small></div><small class="tf-muted" data-pos-return-mode>New item</small></div>
        <div class="row g-2 align-items-end">
            <div class="col-lg-3"><label class="form-label">Product</label><select class="form-select" data-pos-return-product><option value="">Select product</option>@foreach($order->items as $item)@php($returned = $item->posReturnItems->sum('quantity'))@php($returnable = $item->quantity - $returned)@continue($returnable < 1)<option value="{{ $item->id }}" data-name="{{ $item->product_name_snapshot ?: $item->product?->name }}" data-stock="{{ $returnable }}" data-unit="{{ $item->unit ?: $item->product?->unit }}" data-price="{{ $item->unit_price ?: $item->price }}">{{ $item->product_name_snapshot ?: $item->product?->name }}</option>@endforeach</select></div>
            <div class="col-lg-1"><label class="form-label">Available</label><input class="form-control" readonly data-pos-return-stock></div>
            <div class="col-lg-1"><label class="form-label">Quantity</label><input type="number" min="1" value="1" class="form-control" data-pos-return-qty></div>
            <div class="col-lg-2"><label class="form-label">Unit Price</label><input class="form-control" readonly data-pos-return-price></div>
            <div class="col-lg-1"><label class="form-label">Discount</label><input class="form-control" value="Rs 0.00" readonly></div>
            <div class="col-lg-1"><label class="form-label">Tax</label><input class="form-control" value="Rs 0.00" readonly></div>
            <div class="col-lg-2"><label class="form-label">Line Total</label><input class="form-control" value="Rs 0.00" readonly data-pos-return-total></div>
            <div class="col-lg-1 d-grid"><button type="button" class="btn btn-tf-primary" data-pos-return-add aria-label="Add or update return item"><i class="bi bi-check-lg"></i></button></div>
        </div>
        <div class="invalid-feedback d-block d-none mt-2" data-pos-return-error></div>
    </section>
    <div class="table-responsive border rounded" style="max-height: 360px"><table class="table align-middle mb-0"><thead class="sticky-top"><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-pos-return-items><tr data-pos-return-empty><td colspan="9" class="text-center tf-muted py-4">No return items added yet.</td></tr></tbody></table></div>
    <div class="row g-3 mt-3"><div class="col-md-4"><label class="form-label">Refund Method</label><select name="refund_method" class="form-select"><option>Cash</option><option>Store Credit</option><option>Bank Transfer</option></select></div><div class="col-md-8"><label class="form-label">Return Reason</label><input name="reason" class="form-control" required></div><div class="col-12 d-flex gap-2"><button class="btn btn-tf-primary">Process Return</button><a href="{{ route('business.pos.history') }}" class="btn btn-outline-secondary">Cancel</a></div></div>
</form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-pos-return-form]');
    if (!form) return;
    const body = form.querySelector('[data-pos-return-items]'), product = form.querySelector('[data-pos-return-product]'), stock = form.querySelector('[data-pos-return-stock]'), qty = form.querySelector('[data-pos-return-qty]'), price = form.querySelector('[data-pos-return-price]'), total = form.querySelector('[data-pos-return-total]'), error = form.querySelector('[data-pos-return-error]'), mode = form.querySelector('[data-pos-return-mode]');
    let editing = null;
    const money = value => `Rs ${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    const rows = () => [...body.querySelectorAll('[data-pos-return-row]')];
    const option = () => product.selectedOptions[0];
    const sync = () => { const selected = option(); stock.value = selected?.value ? `${selected.dataset.stock} ${selected.dataset.unit || ''}` : ''; price.value = selected?.value ? selected.dataset.price || 0 : ''; total.value = money((Number(qty.value) || 0) * Number(price.value || 0)); };
    const render = () => { rows().forEach((row, index) => { row.querySelector('[data-pos-return-index]').textContent = index + 1; row.querySelectorAll('[data-pos-return-field]').forEach(field => field.name = `items[${index}][${field.dataset.posReturnField}]`); const line = Number(row.dataset.price || 0) * Number(row.querySelector('[data-pos-return-field="quantity"]').value || 0); row.querySelector('[data-pos-return-line-total]').textContent = money(line); }); body.querySelector('[data-pos-return-empty]')?.classList.toggle('d-none', rows().length > 0); };
    const reset = () => { editing = null; product.value = ''; qty.value = 1; stock.value = ''; price.value = ''; total.value = 'Rs 0.00'; mode.textContent = 'New item'; error.classList.add('d-none'); window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); };
    form.querySelector('[data-pos-return-add]').addEventListener('click', () => { const selected = option(), quantity = Number(qty.value || 0), available = Number(selected?.dataset.stock || 0); if (!selected?.value || quantity < 1 || quantity > available) { error.textContent = !selected?.value ? 'Select a product.' : `Return quantity cannot exceed ${available}.`; error.classList.remove('d-none'); return; } const duplicate = rows().find(row => row !== editing && row.dataset.orderItemId === selected.value); const row = editing || duplicate || document.createElement('tr'); row.dataset.posReturnRow = ''; row.dataset.orderItemId = selected.value; row.dataset.stock = available; row.dataset.unit = selected.dataset.unit || ''; row.dataset.price = selected.dataset.price || 0; row.innerHTML = `<td data-pos-return-index></td><td>${selected.text}<input type="hidden" data-pos-return-field="order_item_id" value="${selected.value}"><input type="hidden" data-pos-return-field="quantity" value="${quantity}"></td><td data-pos-return-qty-label>${quantity}</td><td>${money(selected.dataset.price)}</td><td>Rs 0.00</td><td>Rs 0.00</td><td data-pos-return-line-total></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-pos-return-edit>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-pos-return-delete>Delete</button></td>`; if (!editing && !duplicate) body.appendChild(row); render(); reset(); });
    product.addEventListener('change', sync); qty.addEventListener('input', sync);
    body.addEventListener('click', event => { const row = event.target.closest('[data-pos-return-row]'); if (!row) return; if (event.target.closest('[data-pos-return-edit]')) { editing = row; product.value = row.dataset.orderItemId; qty.value = row.querySelector('[data-pos-return-field="quantity"]').value; stock.value = `${row.dataset.stock} ${row.dataset.unit}`; price.value = row.dataset.price; total.value = money(Number(qty.value) * Number(row.dataset.price)); mode.textContent = 'Updating item'; error.classList.add('d-none'); window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); } if (event.target.closest('[data-pos-return-delete]')) { if (editing === row) reset(); row.remove(); render(); } });
    form.addEventListener('submit', event => { if (rows().length) return; event.preventDefault(); error.textContent = 'Add at least one return item before processing.'; error.classList.remove('d-none'); window.getTradeFlowTomSelect?.(product)?.focus(); });
    sync(); render();
});
</script>
@endpush
