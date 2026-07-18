@extends('layouts.dashboard')
@section('page-title', 'Products')
@section('page-subtitle', 'Product catalog, stock, barcode, batch, and archive controls')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
        <h2 class="h5 mb-1">Product Directory</h2>
        <p class="tf-muted mb-0">Search, filter, and manage your product master data.</p>
    </div>
    @companyCan('products.create')
        <a class="btn btn-tf-primary" href="{{ route('business.products.create') }}">+ Add Product</a>
    @endcompanyCan
</div>
<form class="tf-card p-4 mb-3" data-code-lookup-form data-code-lookup-url="{{ route('business.products.lookup') }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Search / Barcode</label><input id="productBarcodeSearch" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name or barcode" autocomplete="off" autofocus data-code-lookup></div>
        <div class="col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">All</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option @selected(request('status')==='Active')>Active</option><option @selected(request('status')==='Inactive')>Inactive</option><option value="Archived" @selected(request('status') === 'Archived' || request('archived'))>Archived</option></select></div>
        <div class="col-md-2"><label class="form-label">Expiry</label><select name="expiry" class="form-select"><option value="">All</option><option value="soon" @selected(request('expiry')==='soon')>Expiring Soon</option><option value="expired" @selected(request('expiry')==='expired')>Expired</option></select></div>
        <div class="col-md-2"><label class="form-label">Batch</label><input name="batch_number" class="form-control" value="{{ request('batch_number') }}"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        @companyCan('products.export')<div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="{{ route('business.products.export') }}">Export</a></div>@endcompanyCan
    </div>
</form>
<x-table class="product-list-table">
    <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Unit</th><th>Latest Purchase</th><th>Average Purchase</th><th>Wholesale</th><th>Stock</th><th>Barcode</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse($products ?? [] as $product)
        <tr>
            <td>@if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" class="rounded border" style="height:38px;width:38px;object-fit:cover">@else<span class="tf-icon-tile bg-light text-primary" style="height:38px;width:38px"><i class="bi bi-box-seam"></i></span>@endif</td>
            <td><strong>{{ $product->name }}</strong><div class="small tf-muted">{{ $product->brand }} {{ $product->warehouse_location ? ' / '.$product->warehouse_location : '' }}</div></td>
            <td>{{ $product->category?->name ?? '-' }}</td>
            <td>{{ $product->unit }}</td>
            <td>{{ $product->latest_purchase_price !== null ? 'Rs '.number_format($product->latest_purchase_price, 2) : '-' }}</td>
            <td>{{ $product->average_purchase_price !== null ? 'Rs '.number_format($product->average_purchase_price, 2) : '-' }}</td>
            <td>Rs {{ number_format($product->wholesale_price, 2) }}</td>
            <td>{{ $product->stock_quantity }}</td>
            <td>{{ $product->barcode ?: '-' }}</td>
            <td><span class="tf-badge {{ $product->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $product->deleted_at ? 'Archived' : $product->status }}</span></td>
            <td>{{ $product->created_at?->format('M d, Y') }}<div class="small tf-muted">{{ $product->creator?->name }}</div></td>
            <td class="text-end text-nowrap">
                @if($product->trashed())
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm">
                            <a class="dropdown-item" href="{{ route('business.products.show', $product->id) }}"><i class="bi bi-eye me-2"></i>View Details</a>
                            @companyCan('products.restore')<form method="POST" action="{{ route('business.products.restore', $product->id) }}">@csrf @method('PATCH')<button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button></form>@endcompanyCan
                            @companyCan('products.delete')<form method="POST" action="{{ route('business.products.destroy', $product->id) }}" onsubmit="return confirm('Delete this archived product permanently if it has no transaction history?')">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Permanently Delete</button></form>@endcompanyCan
                        </div>
                    </div>
                @else
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm">
                            <a class="dropdown-item" href="{{ route('business.products.show', $product) }}"><i class="bi bi-eye me-2"></i>View Details</a>
                            @companyCan('products.edit')
                                <a class="dropdown-item" href="{{ route('business.products.edit', $product) }}"><i class="bi bi-pencil me-2"></i>Edit</a>
                            @endcompanyCan
                            @companyCan('products.archive')
                                <form method="POST" action="{{ route('business.products.archive', $product) }}">@csrf @method('PATCH')<button class="dropdown-item text-warning" type="submit"><i class="bi bi-archive me-2"></i>Archive</button></form>
                            @endcompanyCan
                            @companyCan('products.delete')
                                <form method="POST" action="{{ route('business.products.destroy', $product) }}" onsubmit="return confirm('Delete permanently if unused, otherwise archive?')">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete</button></form>
                            @endcompanyCan
                        </div>
                    </div>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="12" class="text-center tf-muted py-4">No products yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($products) && method_exists($products, 'links'))<div class="mt-3">{{ $products->links() }}</div>@endif
@endsection
