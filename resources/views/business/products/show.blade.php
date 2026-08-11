@extends('layouts.dashboard')
@section('page-title', 'Product Details')
@section('page-subtitle', $product->name ?? 'Product')
@section('content')
@php
    $formatQuantity = static function ($value): string {
        $formatted = number_format((float) ($value ?? 0), 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
@endphp
<div class="row g-4">
    <div class="col-lg-4">
        <div class="tf-card p-4">
            @if($product->image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image) }}" alt="{{ $product->name }}" class="tf-product-img mb-3 w-100 object-fit-cover rounded">
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
                @foreach(['Barcode'=>$product->barcode ?? '-', 'Batch'=>$product->batch_number ?? '-', 'Brand'=>$product->brand ?? '-', 'Manufacturer'=>$product->manufacturer ?? '-', 'Location'=>$product->warehouse_location ?? '-', 'Latest Purchase Price'=>$product->latest_purchase_price !== null ? 'Rs '.number_format($product->latest_purchase_price, 2) : '-', 'Average Purchase Price'=>$product->average_purchase_price !== null ? 'Rs '.number_format($product->average_purchase_price, 2) : '-', 'Wholesale Price'=>'Rs '.number_format($product->wholesale_price, 2), 'Retail Price'=>'Rs '.number_format($product->retail_price, 2), 'Current Stock'=>$formatQuantity($product->stock_quantity), 'Opening Stock'=>$formatQuantity($product->opening_stock ?? $product->stock_quantity), 'Low Stock Alert'=>$formatQuantity($product->low_stock_alert_qty ?? 10), 'Created By'=>$product->creator?->name ?? '-', 'Created Date'=>$product->created_at?->format('n/j/Y'), 'Updated Date'=>$product->updated_at?->format('n/j/Y')] as $k=>$v)
                <div class="col-md-6"><div class="p-3 border rounded"><div class="tf-muted small">{{ $k }}</div><strong>{{ $v }}</strong></div></div>
                @endforeach
            </div>
        </div>
        @php($inventory = $product->inventory)
        @php($isReturnMovement = in_array($latestInventoryMovement?->type, ['PURCHASE_RETURN', 'SALES_RETURN'], true))
        <div class="tf-card p-4 mt-4">
            <h3 class="h5">Inventory Summary</h3>
            <div class="row g-3">
                @foreach(['Current Stock'=>$product->stock_quantity, 'Total Sold'=>$inventory?->sold_stock ?? 0, 'Total Damaged'=>$inventory?->damaged_stock ?? 0, 'Total Sales Returned'=>$inventory?->sales_returned_stock ?? 0, 'Total Purchase Returned'=>$inventory?->purchase_returned_stock ?? 0] as $label => $value)
                    @php($value = $formatQuantity($value))
                    <div class="col-sm-6 col-xl"><div class="p-3 border rounded h-100"><div class="tf-muted small">{{ $label }}</div><strong>{{ $value }}</strong></div></div>
                @endforeach
            </div>
            <div class="border-top mt-3 pt-3"><div class="tf-muted small mb-1">Latest Stock Movement</div>
                @if($latestInventoryMovement)
                    <strong>{{ $latestInventoryMovement->type === 'PURCHASE_RETURN' ? 'Purchase Return' : ($latestInventoryMovement->type === 'SALES_RETURN' ? 'Sales Return' : str_replace('_', ' ', $latestInventoryMovement->type)) }}:</strong>
                    {{ $latestInventoryMovement->previous_stock }} {{ $latestInventoryMovement->type === 'PURCHASE_RETURN' ? '-' : ($latestInventoryMovement->type === 'SALES_RETURN' ? '+' : '→') }} {{ abs((int) $latestInventoryMovement->quantity) }} {{ $isReturnMovement ? '=' : '→' }} {{ $latestInventoryMovement->new_stock }}
                    <span class="tf-muted"><x-date-time :value="$latestInventoryMovement->movement_date ?? $latestInventoryMovement->created_at" /></span>
                @else
                    <span class="tf-muted">No stock movement has been recorded yet.</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
