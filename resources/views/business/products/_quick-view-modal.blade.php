@php
    $imageUrl = $product->image
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($product->image)
        : null;
    $price = static fn ($value): string => $value === null ? '—' : 'Rs '.number_format((float) $value, 2);
    $quantity = static function ($value): string {
        $formatted = number_format((float) ($value ?? 0), 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
    $statusClass = $product->trashed()
        ? 'tf-badge-warning'
        : ($product->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary');
@endphp

<div class="modal fade tf-product-details-modal" id="product-details-{{ $product->id }}" tabindex="-1" aria-labelledby="product-details-title-{{ $product->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="product-details-title-{{ $product->id }}">Product Details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="tf-product-details-summary">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="tf-product-details-image">
                    @else
                        <span class="tf-product-details-placeholder" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                    @endif
                    <div class="min-w-0">
                        <h3>{{ $product->name }}</h3>
                        <p>{{ $product->category?->name ?? 'Uncategorized' }} · {{ $product->unit ?: 'No unit' }}</p>
                        <span class="tf-badge {{ $statusClass }}">{{ $product->trashed() ? 'Archived' : $product->status }}</span>
                    </div>
                </div>

                <dl class="tf-product-details-grid mb-0">
                    @foreach([
                        'Category' => $product->category?->name ?? '—',
                        'Unit' => $product->unit ?: '—',
                        'Brand' => $product->brand ?: '—',
                        'Manufacturer' => $product->manufacturer ?: '—',
                        'Warehouse / Location' => $product->warehouse_location ?: '—',
                        'Retail Price' => $price($product->retail_price),
                        'Wholesale Price' => $price($product->wholesale_price),
                        'Latest Purchase Cost' => $price($product->latest_purchase_price),
                        'Average Cost' => $price($product->average_purchase_price),
                        'Stock' => $quantity($product->stock_quantity),
                        'Barcode' => $product->barcode ?: '—',
                        'Batch / Expiry Tracking' => $product->has_batch_tracking ? 'Enabled' : 'Not enabled',
                        'Created By' => $product->creator?->name ?? '—',
                        'Created At' => $product->created_at?->format('n/j/Y, g:i A') ?? '—',
                    ] as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="{{ route('business.products.show', $product) }}" class="btn btn-tf-primary">Open Product <i class="bi bi-arrow-up-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>
