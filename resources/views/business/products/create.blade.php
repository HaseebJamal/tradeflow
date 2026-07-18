@extends('layouts.dashboard')

@section('page-title', isset($product) ? 'Edit Product' : 'Add Products')
@section('page-subtitle', 'Create product identity, classification, and tracking information.')

@section('content')
@php
    $isEdit = isset($product);
    $draftProducts = old('products', [[]]);
    if (! is_array($draftProducts) || $draftProducts === []) {
        $draftProducts = [[]];
    }
    $cannotSave = ($categories ?? collect())->isEmpty() || ($units ?? collect())->isEmpty();
@endphp

@if($errors->any())
    <div class="alert alert-danger">Please correct the highlighted product fields.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('business.products.update', $product) : route('business.products.store') }}" enctype="multipart/form-data" data-inline-products-form>
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if($isEdit)
        <section class="tf-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Product Information</h2>
                    <p class="tf-muted mb-0">Update the product identity, classification, and tracking information.</p>
                </div>
            </div>
            @include('business.products._master-fields', ['nested' => false, 'index' => 0, 'values' => $product])
            <div class="d-flex justify-content-end mt-4">
                <button class="btn btn-tf-primary" @disabled($cannotSave)>Save Product</button>
            </div>
        </section>
    @else
        <div id="product-sections" class="d-grid gap-3">
            @foreach($draftProducts as $index => $values)
                <section class="tf-card p-4" data-product-section>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h5 mb-1" data-product-heading>Product {{ $loop->iteration }}</h2>
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

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
            <button type="button" class="btn btn-outline-primary" data-add-product-section>+ Add Another Product</button>
            <button class="btn btn-tf-primary" data-save-products @disabled($cannotSave)>Save Products</button>
        </div>
    @endif
</form>
@endsection

@if(!isset($product))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-inline-products-form]');
        const sections = document.getElementById('product-sections');
        const template = document.getElementById('product-section-template');
        const addButton = document.querySelector('[data-add-product-section]');
        if (!form || !sections || !template || !addButton) return;

        const syncBatchFields = (section) => {
            const toggle = section.querySelector('[data-product-batch-toggle]');
            const fields = section.querySelector('[data-product-batch-fields]');
            if (toggle && fields) fields.classList.toggle('d-none', !toggle.checked);
        };

        const updateSections = () => {
            [...sections.querySelectorAll('[data-product-section]')].forEach((section, index) => {
                section.querySelector('[data-product-heading]').textContent = `Product ${index + 1}`;
                const removeButton = section.querySelector('[data-remove-product]');
                if (removeButton) removeButton.hidden = index === 0;

                section.querySelectorAll('[data-product-field]').forEach((field) => {
                    const key = field.dataset.productField;
                    field.name = `products[${index}][${key}]`;
                    field.id = `product-${index}-${key}`;
                });
                section.querySelectorAll('label[for]').forEach((label) => {
                    const key = label.htmlFor.replace(/^product-(?:__INDEX__|\d+)-/, '');
                    if (key) label.htmlFor = `product-${index}-${key}`;
                });
                syncBatchFields(section);
            });
        };

        const initializeSection = (section) => {
            syncBatchFields(section);
            window.initTradeFlowTomSelect?.(section);
        };

        addButton.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            const section = fragment.querySelector('[data-product-section]');
            sections.appendChild(fragment);
            updateSections();
            initializeSection(section);
            section.querySelector('[data-product-field="product_name"]')?.focus();
        });

        sections.addEventListener('change', (event) => {
            if (event.target.matches('[data-product-batch-toggle]')) {
                syncBatchFields(event.target.closest('[data-product-section]'));
            }
        });

        sections.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-product]');
            if (!removeButton) return;
            const section = removeButton.closest('[data-product-section]');
            section.querySelectorAll('select').forEach((select) => select.tomselect?.destroy());
            section.remove();
            updateSections();
        });

        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) return;
            const submit = form.querySelector('[data-save-products]');
            if (submit?.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            if (submit) {
                submit.dataset.submitting = 'true';
                submit.disabled = true;
                submit.textContent = 'Saving Products...';
            }
        });

        updateSections();
        sections.querySelectorAll('[data-product-section]').forEach(initializeSection);
    });
    </script>
    @endpush
@endif
