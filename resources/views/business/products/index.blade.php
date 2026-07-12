@extends('layouts.dashboard')
@section('page-title', 'Products')
@section('page-subtitle', 'Product catalog, stock, barcode, batch, and archive controls')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<form class="tf-card p-4 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Search / Barcode</label><input id="productBarcodeSearch" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, SKU, barcode"></div>
        <div class="col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">All</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option @selected(request('status')==='Active')>Active</option><option @selected(request('status')==='Inactive')>Inactive</option></select></div>
        <div class="col-md-2"><label class="form-label">Expiry</label><select name="expiry" class="form-select"><option value="">All</option><option value="soon" @selected(request('expiry')==='soon')>Expiring Soon</option><option value="expired" @selected(request('expiry')==='expired')>Expired</option></select></div>
        <div class="col-md-2"><label class="form-label">Batch</label><input name="batch_number" class="form-control" value="{{ request('batch_number') }}"></div>
        <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="archived" value="1" @checked(request('archived'))><label class="form-check-label">Archived</label></div></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-2"><button type="button" class="btn btn-outline-secondary w-100" onclick="document.getElementById('productBarcodeSearch')?.focus()">Focus Barcode</button></div>
        @companyCan('products.create')<div class="col-md-2"><a class="btn btn-tf-primary w-100" href="{{ route('business.products.create') }}"><i class="bi bi-plus-lg me-1"></i>Add</a></div>@endcompanyCan
        @companyCan('products.bulk_import')<div class="col-md-2"><a class="btn btn-outline-primary w-100" href="{{ route('business.products.bulk') }}">Bulk Add</a></div>@endcompanyCan
        @companyCan('products.export')<div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="{{ route('business.products.export') }}">Export</a></div>@endcompanyCan
    </div>
</form>
<x-table>
    <thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Unit</th><th>Cost</th><th>Wholesale</th><th>Stock</th><th>Barcode</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    @forelse($products ?? [] as $product)
        <tr>
            <td>{{ $product->sku }}</td>
            <td><strong>{{ $product->name }}</strong><div class="small tf-muted">{{ $product->brand }} {{ $product->warehouse_location ? ' / '.$product->warehouse_location : '' }}</div></td>
            <td>{{ $product->category?->name ?? '-' }}</td>
            <td>{{ $product->unit }}</td>
            <td>Rs {{ number_format($product->purchase_cost) }}</td>
            <td>Rs {{ number_format($product->wholesale_price) }}</td>
            <td>{{ $product->stock_quantity }}</td>
            <td>{{ $product->barcode ?: '-' }}</td>
            <td><span class="tf-badge {{ $product->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $product->deleted_at ? 'Archived' : $product->status }}</span></td>
            <td>{{ $product->created_at?->format('M d, Y') }}<div class="small tf-muted">{{ $product->creator?->name }}</div></td>
            <td class="text-end">
                @if($product->trashed() && app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'products.restore'))
                    <form method="POST" action="{{ route('business.products.restore', $product->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Restore</button></form>
                @else
                    <a href="{{ route('business.products.show', $product) }}" class="btn btn-sm btn-outline-primary">Details</a>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="11" class="text-center tf-muted py-4">No products yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($products) && method_exists($products, 'links'))<div class="mt-3">{{ $products->links() }}</div>@endif
@endsection
