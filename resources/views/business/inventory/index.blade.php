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
<div class="tf-card p-4 mb-4">
    <h2 class="h5">Stock Adjustment</h2>
    <form method="POST" action="{{ route('business.inventory.adjust') }}" class="row g-3">@csrf
        <div class="col-md-4"><select name="product_id" class="form-select">@foreach(($inventories ?? collect())->pluck('product')->filter() as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="type" class="form-select"><option value="added">Add Stock</option><option value="reduced">Reduce Stock</option><option value="adjustment">Adjust</option><option value="returned">Returned</option><option value="damaged">Damaged</option></select></div>
        <div class="col-md-2"><input name="quantity" type="number" class="form-control" placeholder="Qty"></div>
        <div class="col-md-3"><input name="note" class="form-control" placeholder="Note"></div>
        <div class="col-md-1"><button class="btn btn-tf-primary w-100"><i class="bi bi-check-lg"></i></button></div>
    </form>
</div>
<x-table><thead><tr><th>Product</th><th>Available</th><th>Sold</th><th>Damaged</th><th>Returned</th><th>Alert Qty</th><th>Update Alert</th></tr></thead><tbody>@forelse($inventories ?? [] as $inventory)<tr><td>{{ $inventory->product?->name }}</td><td>{{ $inventory->product?->stock_quantity ?? $inventory->available_stock }}</td><td>{{ $inventory->sold_stock }}</td><td>{{ $inventory->damaged_stock }}</td><td>{{ $inventory->returned_stock }}</td><td>{{ $inventory->product?->low_stock_alert_qty ?? $inventory->low_stock_alert }}</td><td>@if($inventory->product)<form method="POST" action="{{ route('business.products.low-stock-alert', $inventory->product) }}" class="d-flex gap-2">@csrf @method('PATCH')<input name="low_stock_alert_qty" type="number" min="0" value="{{ $inventory->product->low_stock_alert_qty ?? 10 }}" class="form-control form-control-sm" style="max-width:90px"><button class="btn btn-sm btn-outline-primary">Save</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="text-center tf-muted py-4">No inventory records.</td></tr>@endforelse</tbody></x-table>
<div class="tf-card p-4 mt-4"><h2 class="h5">Stock History</h2><x-table><thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Note</th><th>By</th></tr></thead><tbody>@forelse($movements ?? [] as $move)<tr><td>{{ $move->created_at->format('M d, Y') }}</td><td>{{ $move->product?->name }}</td><td>{{ ucfirst($move->type) }}</td><td>{{ $move->quantity }}</td><td>{{ $move->note ?? $move->reason }}</td><td>{{ $move->user?->name }}</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No stock history.</td></tr>@endforelse</tbody></x-table></div>
@endsection
