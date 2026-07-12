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
    <form method="POST" action="{{ route('business.products.bulk.store') }}">
        @csrf
        <div class="table-responsive">
            <table class="table" data-bulk-products>
                <thead><tr><th>Product Name</th><th>Category</th><th>Unit</th><th>Purchase Cost</th><th>Wholesale Price</th><th>Retail Price</th><th>Opening Stock</th><th>SKU</th><th>Barcode</th><th>Batch</th><th>Expiry</th><th>Low Stock</th><th></th></tr></thead>
                <tbody>
                    @for($i=0;$i<3;$i++)
                        <tr data-bulk-row>
                            <td><input name="products[{{ $i }}][name]" class="form-control" required></td>
                            <td><input name="products[{{ $i }}][category]" class="form-control" required></td>
                            <td><select name="products[{{ $i }}][unit]" class="form-select">@foreach(['Piece','Carton','KG','Liter'] as $unit)<option>{{ $unit }}</option>@endforeach</select></td>
                            <td><input name="products[{{ $i }}][purchase_cost]" type="number" step="0.01" min="0" class="form-control" required></td>
                            <td><input name="products[{{ $i }}][wholesale_price]" type="number" step="0.01" min="0" class="form-control" required></td>
                            <td><input name="products[{{ $i }}][retail_price]" type="number" step="0.01" min="0" class="form-control"></td>
                            <td><input name="products[{{ $i }}][opening_stock]" type="number" min="0" class="form-control" required></td>
                            <td><input name="products[{{ $i }}][sku]" class="form-control"></td>
                            <td><input name="products[{{ $i }}][barcode]" class="form-control"></td>
                            <td><input name="products[{{ $i }}][batch_number]" class="form-control"></td>
                            <td><input name="products[{{ $i }}][expiry_date]" type="date" class="form-control"></td>
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
