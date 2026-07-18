@extends('layouts.dashboard')
@section('page-title', 'Product Details')
@section('page-subtitle', $product->name ?? 'Product')
@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="tf-card p-4">
            @if($product->image)
                <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" class="tf-product-img mb-3 w-100 object-fit-cover rounded">
            @else
                <div class="tf-product-img mb-3 d-flex align-items-center justify-content-center"><i class="bi bi-box-seam fs-1 text-primary"></i></div>
            @endif
            <h2 class="h4">{{ $product->name }}</h2>
            <p class="tf-muted">{{ $product->category?->name ?? 'Uncategorized' }}</p>
            <span class="tf-badge {{ $product->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $product->status }}</span>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="tf-card p-4">
            <h3 class="h5">Product Information</h3>
            <div class="row g-3">
                @foreach(['Barcode'=>$product->barcode ?? '-', 'Batch'=>$product->batch_number ?? '-', 'Brand'=>$product->brand ?? '-', 'Manufacturer'=>$product->manufacturer ?? '-', 'Location'=>$product->warehouse_location ?? '-', 'Latest Purchase Price'=>$product->latest_purchase_price !== null ? 'Rs '.number_format($product->latest_purchase_price, 2) : '-', 'Average Purchase Price'=>$product->average_purchase_price !== null ? 'Rs '.number_format($product->average_purchase_price, 2) : '-', 'Wholesale / Selling Price'=>'Rs '.number_format($product->wholesale_price, 2), 'Retail Price'=>'Rs '.number_format($product->retail_price, 2), 'Current Stock'=>$product->stock_quantity, 'Opening Stock'=>$product->opening_stock ?? $product->stock_quantity, 'Low Stock Alert'=>$product->low_stock_alert_qty ?? 10, 'Created By'=>$product->creator?->name ?? '-', 'Created Date'=>$product->created_at?->format('M d, Y'), 'Updated Date'=>$product->updated_at?->format('M d, Y')] as $k=>$v)
                <div class="col-md-6"><div class="p-3 border rounded"><div class="tf-muted small">{{ $k }}</div><strong>{{ $v }}</strong></div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
