@extends('layouts.dashboard')
@section('page-title', 'Cart')
@section('page-subtitle', 'Create retailer order')
@section('content')
@php $firstBusiness = ($products ?? collect())->first()?->business_id; @endphp
<form method="POST" action="{{ route('retailer.cart.order') }}" class="row g-4">@csrf
    <div class="col-lg-8">
        <input type="hidden" name="business_id" value="{{ $firstBusiness }}">
        <x-table><thead><tr><th>Product</th><th>Supplier</th><th>Qty</th><th>Price</th></tr></thead><tbody>
            @forelse($products ?? [] as $product)
            <tr><td>{{ $product->name }}<input type="hidden" name="products[{{ $loop->index }}][id]" value="{{ $product->id }}"></td><td>{{ $product->business?->business_name }}</td><td><input name="products[{{ $loop->index }}][quantity]" type="number" min="0" class="form-control" value="{{ $loop->first ? 1 : 0 }}" style="max-width:100px"></td><td>Rs {{ number_format($product->wholesale_price) }}</td></tr>
            @empty
            <tr><td colspan="4" class="text-center tf-muted py-4">No products available.</td></tr>
            @endforelse
        </tbody></x-table>
    </div>
    <div class="col-lg-4"><div class="tf-card p-4"><h2 class="h5">Order Summary</h2><p class="tf-muted">Set quantity to 0 for items you do not want. Orders are submitted manually for supplier approval.</p><button class="btn btn-tf-primary w-100" @disabled(empty($firstBusiness))>Place Order</button></div></div>
</form>
@endsection
