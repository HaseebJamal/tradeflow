<div id="product-sections" class="d-grid gap-3">
    @foreach(($draftProducts ?? [[]]) as $index => $values)
        <section class="tf-card p-4" data-product-section>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1" data-product-heading>{{ $loop->first ? 'Product' : 'Product '.$loop->iteration }}</h2>
                    <p class="tf-muted mb-0">Product identity and tracking details.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-product @if($loop->first) hidden @endif><i class="bi bi-trash me-1"></i>Remove Product</button>
            </div>
            @include('business.products._master-fields', ['nested' => true, 'index' => $index, 'values' => $values])
        </section>
    @endforeach
</div>

<template id="product-section-template">
    <section class="tf-card p-4" data-product-section>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1" data-product-heading>Product</h2>
                <p class="tf-muted mb-0">Product identity and tracking details.</p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-product><i class="bi bi-trash me-1"></i>Remove Product</button>
        </div>
        @include('business.products._master-fields', ['nested' => true, 'index' => '__INDEX__', 'values' => []])
    </section>
</template>

@unless($hideProductFormActions ?? false)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
        <button type="button" class="btn btn-outline-primary" data-add-product-section>+ Add Another Product</button>
        <button type="submit" class="btn btn-tf-primary" data-save-products>Save Products</button>
    </div>
@endunless
