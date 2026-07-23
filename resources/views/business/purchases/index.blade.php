@extends('layouts.dashboard')
@section('page-title', 'Purchases')
@section('page-subtitle', 'Purchase orders, goods received, supplier invoices, payments, and returns')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div><h2 class="h5 mb-1">All Purchases</h2><p class="tf-muted mb-0">Track supplier commitments through receiving, payment, and return.</p></div>
    <div>
        @companyCan('purchases.create')
            @if($hasSuppliers)
                <a href="{{ route('business.purchases.index', ['create' => 1]) }}#purchase-create" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>New Purchase</a>
            @else
                <button type="button" class="btn btn-tf-primary" data-purchase-no-supplier data-create-supplier-url="{{ route('business.suppliers.create') }}"><i class="bi bi-plus-lg me-1"></i>New Purchase</button>
            @endif
        @endcompanyCan
    </div>
</div>

@if($showPurchaseCreate)
<section id="purchase-create" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Create Purchase</h2><a href="{{ route('business.purchases.index') }}" class="btn btn-sm btn-outline-secondary">Close</a></div>
    @include('business.purchases._form')
</section>
@endif

<form class="tf-card p-3 mb-3 row g-2 align-items-end" data-code-lookup-form data-code-lookup-url="{{ route('business.purchases.lookup') }}">
    <div class="col-md-3"><label class="form-label">Search</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Purchase number, invoice, or supplier" autocomplete="off" data-code-lookup autofocus></div>
    <div class="col-md-2"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Ordered','Received','Partially Returned','Returned'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', now(config('app.timezone'))->toDateString()) }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', now(config('app.timezone'))->toDateString()) }}" class="form-control"></div>
    <div class="col-md-1 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('business.purchases.index') }}" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
</form>

<x-table><thead><tr><th>Purchase order</th><th>Supplier</th><th>Invoice</th><th>Ordered</th><th>Received</th><th>Total</th><th>Paid / Balance</th><th>Status</th><th></th></tr></thead><tbody>@forelse($purchases as $purchase)<tr><td><strong>{{ $purchase->purchase_number }}</strong><small class="d-block tf-muted"><x-date-time :value="$purchase->purchase_date" /></small></td><td>{{ $purchase->supplier?->supplier_name }}</td><td>{{ $purchase->invoice?->invoice_number ?: ($purchase->supplier_invoice_number ?: 'Pending receipt') }}</td><td>Rs {{ number_format($purchase->grand_total, 2) }}</td><td>Rs {{ number_format($purchase->invoice?->grand_total ?? 0, 2) }}</td><td>Rs {{ number_format($purchase->grand_total, 2) }}</td><td>Rs {{ number_format($purchase->paid_amount, 2) }}<small class="d-block tf-muted">Balance Rs {{ number_format($purchase->balance, 2) }}</small></td><td><span class="tf-badge {{ $purchase->status === 'Received' ? 'tf-badge-success' : ($purchase->status === 'Ordered' ? 'tf-badge-warning' : 'tf-badge-info') }}">{{ $purchase->status }}</span></td><td><a href="{{ route('business.purchases.show', $purchase) }}" class="btn btn-sm btn-outline-primary">Manage</a></td></tr>@empty<tr><td colspan="9" class="text-center tf-muted py-5">No purchase orders found.</td></tr>@endforelse</tbody></x-table><div class="mt-3">{{ $purchases->links() }}</div>
@endsection

@if(! $hasSuppliers)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const showNoSupplier = function (url) {
        if (!window.Swal) {
            if (window.confirm('You must create at least one supplier before creating a purchase. Create supplier now?')) window.location.assign(url);
            return;
        }
        window.Swal.fire({
            icon: 'warning',
            title: 'No Supplier Found',
            text: 'You must create at least one supplier before creating a purchase.',
            showCancelButton: true,
            confirmButtonText: 'Create Supplier',
            cancelButtonText: 'Cancel',
        }).then(function (result) {
            if (result.isConfirmed) window.location.assign(url);
        });
    };

    document.querySelectorAll('[data-purchase-no-supplier]').forEach(function (button) {
        button.addEventListener('click', function () { showNoSupplier(button.dataset.createSupplierUrl); });
    });

    @if($showPurchaseCreate)
        showNoSupplier(@json(route('business.suppliers.create')));
    @endif
});
</script>
@endpush
@endif
