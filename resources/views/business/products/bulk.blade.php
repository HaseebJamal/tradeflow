@extends('layouts.dashboard')
@section('page-title', 'Bulk Add Products')
@section('page-subtitle', 'Save multiple products in one transaction')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4">
    <div class="d-flex flex-wrap gap-2 mb-3">
        @companyCan('products.export')<a href="{{ route('business.products.template') }}" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>Download CSV Template</a>@endcompanyCan
        <a href="{{ route('business.products.index') }}" class="btn btn-outline-secondary">Back to Products</a>
    </div>
    <form method="POST" action="{{ route('business.products.bulk.store') }}" data-bulk-product-price-form>
        @csrf
        <div class="table-responsive">
            <table class="table align-middle" data-bulk-products>
                <thead><tr><th>Product Name</th><th>Category</th><th>Unit</th><th>Purchase Cost</th><th>Wholesale Price</th><th>Retail Price</th><th>Barcode</th><th>Batch</th><th>Expiry Date (Optional)</th><th>Low Stock</th><th></th></tr></thead>
                <tbody>
                    @for($i=0;$i<3;$i++)
                        <tr data-bulk-row>
                            <td><input name="products[{{ $i }}][name]" class="form-control" required></td>
                            <td><input name="products[{{ $i }}][category]" class="form-control" required></td>
                            <td><select name="products[{{ $i }}][unit]" class="form-select">@foreach(['Piece','Carton','KG','Liter'] as $unit)<option>{{ $unit }}</option>@endforeach</select></td>
                            <td><input name="products[{{ $i }}][purchase_cost]" type="number" step="0.01" min="0" class="form-control" required data-purchase-price></td>
                            <td><input name="products[{{ $i }}][wholesale_price]" type="number" step="0.01" min="0" class="form-control" required data-selling-price></td>
                            <td><input name="products[{{ $i }}][retail_price]" type="number" step="0.01" min="0" class="form-control"></td>
                            <td><div class="form-control bg-light text-muted">Auto generated</div></td>
                            <td><input name="products[{{ $i }}][batch_number]" class="form-control"></td>
                            <td class="bulk-expiry-cell">
                                <div class="form-check mb-1">
                                    <input name="products[{{ $i }}][has_batch_tracking]" value="1" type="checkbox" class="form-check-input" id="bulkExpiryTracking{{ $i }}" data-bulk-expiry-toggle>
                                    <label class="form-check-label small" for="bulkExpiryTracking{{ $i }}">Expiry required</label>
                                </div>
                                <input name="products[{{ $i }}][expiry_date]" type="date" class="form-control" disabled data-bulk-expiry-date aria-label="Expiry Date (Optional)">
                            </td>
                            <td><input name="products[{{ $i }}][low_stock_alert_qty]" type="number" min="0" value="10" class="form-control"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-bulk-row>&times;</button></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-outline-primary" data-add-bulk-row>Add Row</button>
        <button class="btn btn-tf-primary">Save All Products</button>
    </form>
</div>
@endsection
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>{const form=document.querySelector('[data-bulk-product-price-form]');if(!form)return;const message='Selling Price must be greater than Purchase Price.',today=@json(now()->toDateString()),syncExpiry=row=>{const toggle=row.querySelector('[data-bulk-expiry-toggle]'),date=row.querySelector('[data-bulk-expiry-date]');if(!toggle||!date)return;date.disabled=!toggle.checked;if(toggle.checked&&!date.value)date.value=today;if(!toggle.checked)date.value=''},validateRow=row=>{const cost=row.querySelector('[data-purchase-price]'),sell=row.querySelector('[data-selling-price]');if(!cost||!sell)return true;const invalid=Number.isFinite(Number(cost.value))&&Number.isFinite(Number(sell.value))&&Number(sell.value)<=Number(cost.value);sell.classList.toggle('is-invalid',invalid);sell.setCustomValidity(invalid?message:'');return !invalid},validate=()=>[...form.querySelectorAll('[data-bulk-row]')].every(validateRow);form.querySelectorAll('[data-bulk-row]').forEach(syncExpiry);form.addEventListener('change',event=>{if(event.target.matches('[data-bulk-expiry-toggle]'))syncExpiry(event.target.closest('[data-bulk-row]'))});form.addEventListener('input',event=>{if(event.target.matches('[data-purchase-price],[data-selling-price]'))validateRow(event.target.closest('[data-bulk-row]'))});form.addEventListener('submit',event=>{if(!validate()){event.preventDefault();const bad=form.querySelector('.is-invalid');bad?.focus()}})});</script>@endpush
