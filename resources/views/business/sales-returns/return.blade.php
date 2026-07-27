@extends('layouts.dashboard')
@section('page-title', 'Sales Return')
@section('page-subtitle', 'Select the items and quantities to return from sale '.$order->order_number)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.sales.returns.store', $order) }}" class="tf-card p-4" data-sales-return-form>
    @csrf
    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th>Return</th><th>Product</th><th>Sold Qty</th><th>Already Returned</th><th>Returnable</th><th>Return Qty</th></tr></thead>
            <tbody>
                @php($returnableItems = 0)
                @foreach($order->items as $index => $item)
                    @php($returned = (int) $item->salesReturnItems->sum('quantity'))
                    @php($returnable = max(0, (int) $item->quantity - $returned))
                    @continue($returnable < 1)
                    @php($returnableItems++)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input" data-sales-return-toggle aria-label="Return {{ $item->product_name_snapshot ?: $item->product?->name }}">
                            <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $item->id }}" disabled data-sales-return-field>
                        </td>
                        <td>{{ $item->product_name_snapshot ?: $item->product?->name ?: 'Deleted Product' }}</td>
                        <td><x-quantity :value="$item->quantity" /></td>
                        <td><x-quantity :value="$returned" /></td>
                        <td><x-quantity :value="$returnable" /></td>
                        <td><input type="number" min="1" max="{{ $returnable }}" step="1" inputmode="numeric" name="items[{{ $index }}][quantity]" value="0" class="form-control js-whole-number" disabled data-sales-return-field></td>
                    </tr>
                @endforeach
                @if($returnableItems === 0)<tr><td colspan="6" class="text-center tf-muted py-4">No items are available for return.</td></tr>@endif
            </tbody>
        </table>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-md-4"><label class="form-label">Refund Method</label><select name="refund_method" class="form-select"><option value="Cash">Cash</option><option value="Store Credit">Store Credit</option><option value="Bank Transfer">Bank Transfer</option></select></div>
        <div class="col-md-8"><label class="form-label">Return Reason</label><input name="reason" class="form-control" value="{{ old('reason') }}" required></div>
        <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-tf-primary" @disabled($returnableItems === 0)>Process Return</button><a href="{{ route('business.sales.returns.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </div>
</form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-sales-return-form]');
    if (!form || form.dataset.initialized === '1') return;
    form.dataset.initialized = '1';
    form.addEventListener('change', (event) => {
        const toggle = event.target.closest('[data-sales-return-toggle]');
        if (!toggle) return;
        toggle.closest('tr').querySelectorAll('[data-sales-return-field]').forEach((field) => {
            field.disabled = !toggle.checked;
        });
    });
    form.addEventListener('submit', (event) => {
        const invalidQuantity = [...form.querySelectorAll('[data-sales-return-toggle]:checked')]
            .map((toggle) => toggle.closest('tr').querySelector('[name$="[quantity]"]'))
            .find((field) => Number(field?.value || 0) < 1);

        if (!invalidQuantity) return;

        event.preventDefault();
        invalidQuantity.focus();
        invalidQuantity.reportValidity();
    });
});
</script>
@endpush
