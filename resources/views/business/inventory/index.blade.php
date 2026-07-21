@extends('layouts.dashboard')
@section('page-title', 'Inventory')
@section('page-subtitle', 'Stock table and low stock alerts')
@section('content')
<div class="row g-3 mb-4">
    @forelse($lowStockProducts ?? [] as $product)
    <div class="col-md-4"><div class="tf-card p-3 border-danger"><i class="bi bi-exclamation-triangle text-danger me-2"></i>{{ $product->name }} - {{ $product->stock_quantity }} left. Alert at {{ $product->low_stock_alert_qty }}.</div></div>
    @empty
    <div class="col-12"><div class="tf-card p-3">No low stock alerts.</div></div>
    @endforelse
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Inventory Control</h2><p class="tf-muted mb-0">Manage available stock and stock movement history.</p></div>
    @companyCan('products.create')<a href="{{ route('business.products.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Add Product</a>@endcompanyCan
</div>
@companyCan('inventory.adjust_stock')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Stock Adjustment</h2>
    <form method="POST" action="{{ route('business.inventory.adjust') }}" class="row g-3">@csrf
        <div class="col-md-4"><select name="product_id" class="form-select">@foreach(($inventories ?? collect())->pluck('product')->filter() as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="type" class="form-select"><option value="added">Add Stock</option><option value="reduced">Reduce Stock</option><option value="adjustment">Set Stock Qty</option><option value="returned">Returned</option><option value="damaged">Damaged</option></select></div>
        <div class="col-md-2"><input name="quantity" type="number" min="0" class="form-control" placeholder="Qty"></div>
        <div class="col-md-3"><input name="note" class="form-control" placeholder="Note"></div>
        <div class="col-md-1"><button class="btn btn-tf-primary w-100"><i class="bi bi-check-lg"></i></button></div>
    </form>
</div>@endcompanyCan
@companyCan('inventory.stock_transfer')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Stock Transfer</h2>
    <form method="POST" action="{{ route('business.inventory.transfer') }}" class="row g-3">@csrf
        <div class="col-md-4"><select name="product_id" class="form-select">@foreach(($inventories ?? collect())->pluck('product')->filter() as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><input name="quantity" type="number" min="1" class="form-control" placeholder="Qty" required></div>
        <div class="col-md-5"><input name="note" class="form-control" placeholder="Destination or transfer reference" required></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-left-right"></i></button></div>
    </form>
</div>@endcompanyCan
<x-table>
    <thead><tr><th>Product</th><th>Available</th><th>Sold</th><th>Damaged</th><th>Sales Returned</th><th>Purchase Returned</th><th>Alert Qty</th><th>Last Updated</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($inventories ?? [] as $inventory)
        <tr>
            <td>{{ $inventory->product?->name }}</td><td>{{ $inventory->product?->stock_quantity ?? $inventory->available_stock }}</td><td>{{ $inventory->sold_stock }}</td><td>{{ $inventory->damaged_stock }}</td><td>{{ $inventory->sales_returned_stock ?? 0 }}</td><td>{{ $inventory->purchase_returned_stock ?? 0 }}</td><td>{{ $inventory->product?->low_stock_alert_qty ?? $inventory->low_stock_alert }}</td><td><x-date-time :value="$inventory->updated_at" /></td>
            <td>
                @companyCan('inventory.low_stock_alerts')
                    @if($inventory->product)
                        <form method="POST" action="{{ route('business.products.low-stock-alert', $inventory->product) }}" class="d-flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input name="low_stock_alert_qty" type="number" min="0" value="{{ $inventory->product->low_stock_alert_qty ?? 10 }}" class="form-control form-control-sm" style="max-width:90px">
                            <button class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    @endif
                @endcompanyCan
                @if($inventory->product)
                    <div class="d-flex gap-1 mt-2">
                        @companyCan('products.edit')<a href="{{ route('business.products.edit', $inventory->product) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endcompanyCan
                        @companyCan('products.delete')<form method="POST" action="{{ route('business.products.destroy', $inventory->product) }}" onsubmit="return confirm('Delete or archive this product?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcompanyCan
                    </div>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center tf-muted py-4">No inventory records.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="tf-card p-4 mt-4"><h2 class="h5">Stock History</h2><x-table><thead><tr><th>Date &amp; Time</th><th>Product</th><th>Movement Type</th><th>Stock Before</th><th>Quantity</th><th>Operation</th><th>Stock After</th><th>Reference</th><th>User</th></tr></thead><tbody>@forelse($movements ?? [] as $move)@php($isReturn = in_array($move->type, ['PURCHASE_RETURN', 'SALES_RETURN'], true))@php($operation = $move->type === 'PURCHASE_RETURN' ? '-' : ($move->type === 'SALES_RETURN' ? '+' : '—'))<tr><td><x-date-time :value="$move->movement_date ?? $move->created_at" /></td><td>{{ $move->product?->name ?? 'Deleted Product' }}</td><td>{{ $move->type === 'PURCHASE_RETURN' ? 'Purchase Return' : ($move->type === 'SALES_RETURN' ? 'Sales Return' : str_replace('_', ' ', $move->type)) }}</td><td>{{ $move->previous_stock }}</td><td>{{ abs((int) $move->quantity) }}</td><td>{{ $operation }}</td><td>{{ $move->new_stock }}</td><td>{{ $isReturn ? $move->note : '—' }}</td><td>{{ $move->creator?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="9" class="text-center tf-muted py-4">No stock history.</td></tr>@endforelse</tbody></x-table></div>
@endsection
