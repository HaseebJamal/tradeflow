@extends('layouts.dashboard')
@section('page-title', 'Product Details')
@section('page-subtitle', $product->name ?? 'Product')
@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="tf-card p-4">
            <div class="tf-product-img mb-3" @if(!empty($product->image)) style="background:url('{{ asset('storage/'.$product->image) }}') center/cover" @endif></div>
            <h2 class="h4">{{ $product->name }}</h2>
            <p class="tf-muted">{{ $product->category?->name ?? 'Uncategorized' }}</p>
            <span class="tf-badge {{ $product->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $product->status }}</span>
            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('business.products.edit', $product) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                <form method="POST" action="{{ route('business.products.destroy', $product) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete</button></form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="tf-card p-4">
            <h3 class="h5">Product Information</h3>
            <div class="row g-3">
                @foreach(['SKU'=>$product->sku ?? '-', 'Barcode'=>$product->barcode ?? '-', 'Batch'=>$product->batch_number ?? '-', 'Wholesale Price'=>'Rs '.number_format($product->wholesale_price), 'Retail Price'=>'Rs '.number_format($product->retail_price), 'Available Stock'=>$product->stock_quantity, 'MOQ'=>$product->minimum_order_quantity, 'Low Stock Alert'=>$product->low_stock_alert_qty ?? 10] as $k=>$v)
                <div class="col-md-6"><div class="p-3 border rounded"><div class="tf-muted small">{{ $k }}</div><strong>{{ $v }}</strong></div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
