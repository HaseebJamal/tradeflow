@extends('layouts.dashboard')
@section('page-title', 'Browse Products')
@section('page-subtitle', 'Retailer product cards')
@section('content')
<div class="row g-4">
@forelse($products ?? [] as $product)
<div class="col-md-6 col-xl-4"><div class="tf-card p-3 h-100"><div class="tf-product-img mb-3" @if($product->image_url) style="background:url('{{ $product->image_url }}') center/cover" @endif></div><h2 class="h5">{{ $product->name }}</h2><p class="tf-muted mb-1">{{ $product->business?->business_name }}</p><div class="d-flex justify-content-between align-items-center"><strong>Rs {{ number_format($product->wholesale_price) }}</strong><a href="{{ route('retailer.cart') }}" class="btn btn-sm btn-tf-primary"><i class="bi bi-cart-plus"></i></a></div></div></div>
@empty
<div class="col-12"><div class="tf-card p-4 text-center tf-muted">No products available.</div></div>
@endforelse
</div>
@if(isset($products) && method_exists($products, 'links'))<div class="mt-3">{{ $products->links() }}</div>@endif
@endsection
