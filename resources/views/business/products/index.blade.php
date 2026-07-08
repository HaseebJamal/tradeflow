@extends('layouts.dashboard')
@section('page-title', 'Products')
@section('page-subtitle', 'Product catalog list')
@section('content')
<form class="row g-2 align-items-end mb-3"><div class="col-md-5"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, SKU, barcode"></div><div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option @selected(request('status')==='Active')>Active</option><option @selected(request('status')==='Inactive')>Inactive</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div><div class="col-md-2"><a class="btn btn-tf-primary w-100" href="{{ route('business.products.create') }}"><i class="bi bi-plus-lg me-1"></i>Add</a></div></form>
<x-table>
    <thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Unit</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($products ?? [] as $product)
        <tr><td>{{ $product->sku }}</td><td>{{ $product->name }}</td><td>{{ $product->category?->name ?? '-' }}</td><td>{{ $product->unit }}</td><td>Rs {{ number_format($product->wholesale_price) }}</td><td>{{ $product->stock_quantity }}</td><td>{{ $product->status }}</td><td><a href="{{ route('business.products.show', $product) }}" class="btn btn-sm btn-outline-primary">Details</a></td></tr>
    @empty
        <tr><td colspan="8" class="text-center tf-muted py-4">No products yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($products) && method_exists($products, 'links'))<div class="mt-3">{{ $products->links() }}</div>@endif
@endsection
