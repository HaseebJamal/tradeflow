@extends('layouts.dashboard')
@section('page-title', isset($product) ? 'Edit Product' : 'Add Product')
@section('page-subtitle', 'Product form')
@section('content')
<div class="tf-card p-4">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ isset($product) ? route('business.products.update', $product) : route('business.products.store') }}" enctype="multipart/form-data" class="row g-3">
        @csrf
        @isset($product) @method('PUT') @endisset
        <div class="col-md-6"><label class="form-label">Product name</label><input name="product_name" class="form-control" value="{{ old('product_name', $product->name ?? '') }}"></div>
        <div class="col-md-6"><label class="form-label">Category</label><input name="category" class="form-control" value="{{ old('category', $product->category?->name ?? '') }}" placeholder="Grocery"></div>
        @foreach(['sku'=>'SKU','barcode'=>'Barcode','batch_number'=>'Batch number'] as $name=>$label)<div class="col-md-4"><label class="form-label">{{ $label }}</label><input name="{{ $name }}" class="form-control" value="{{ old($name, $product->$name ?? '') }}"></div>@endforeach
        <div class="col-md-4"><label class="form-label">Expiry date</label><input name="expiry_date" type="date" class="form-control" value="{{ old('expiry_date', (isset($product) && $product->expiry_date) ? $product->expiry_date->format('Y-m-d') : '') }}"></div>
        <div class="col-md-4"><label class="form-label">Retail price</label><input name="retail_price" type="number" step="0.01" class="form-control" value="{{ old('retail_price', $product->retail_price ?? 0) }}"></div>
        <div class="col-md-4"><label class="form-label">Wholesale price</label><input name="wholesale_price" type="number" step="0.01" class="form-control" value="{{ old('wholesale_price', $product->wholesale_price ?? 0) }}"></div>
        <div class="col-md-4"><label class="form-label">Purchase cost</label><input name="purchase_cost" type="number" step="0.01" class="form-control" value="{{ old('purchase_cost', $product->purchase_cost ?? 0) }}"></div>
        <div class="col-md-4"><label class="form-label">Minimum order quantity</label><input name="minimum_order_quantity" type="number" class="form-control" value="{{ old('minimum_order_quantity', $product->minimum_order_quantity ?? 1) }}"></div>
        <div class="col-md-4"><label class="form-label">Stock quantity</label><input name="stock_quantity" type="number" class="form-control" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}"></div>
        <div class="col-md-4"><label class="form-label">Low stock alert quantity</label><input name="low_stock_alert_qty" type="number" min="0" class="form-control" value="{{ old('low_stock_alert_qty', $product->low_stock_alert_qty ?? 10) }}"></div>
        <div class="col-md-4"><label class="form-label">Unit</label><select name="unit" class="form-select">@foreach(['Piece','Carton','KG','Liter'] as $unit)<option @selected(old('unit', $product->unit ?? 'Piece') === $unit)>{{ $unit }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option>Active</option><option>Inactive</option></select></div>
        <div class="col-md-8"><label class="form-label">Product image</label><input name="product_image" type="file" class="form-control"></div>
        <div class="col-12"><button class="btn btn-tf-primary">Save Product</button></div>
    </form>
</div>
@endsection
