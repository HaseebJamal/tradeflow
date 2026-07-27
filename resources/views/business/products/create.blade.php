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

<form method="POST" action="{{ $isEdit ? route('business.products.update', $product) : route('business.products.store') }}" enctype="multipart/form-data" data-inline-products-form data-inline-category-url="{{ route('business.categories.store') }}" data-inline-unit-url="{{ route('business.units.store') }}">
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
                <button class="btn btn-tf-primary" data-save-products @disabled($cannotSave)>Save Product</button>
            </div>
        </section>
    @else
        <div id="product-sections" class="d-grid gap-3">
            @foreach($draftProducts as $index => $values)
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

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
            <button type="button" class="btn btn-outline-primary" data-add-product-section>+ Add Another Product</button>
            <button class="btn btn-tf-primary" data-save-products @disabled($cannotSave)>Save Products</button>
        </div>
    @endif
</form>

@companyCan('categories.create')
<div class="modal fade" id="inlineProductCategoryModal" tabindex="-1" aria-hidden="true" aria-labelledby="inlineProductCategoryModalTitle">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form data-inline-catalog-form="category">
            <div class="modal-header"><h2 class="modal-title fs-5" id="inlineProductCategoryModalTitle">New Category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="alert alert-danger d-none" data-inline-catalog-errors role="alert"></div>
                <div class="mb-3"><label class="form-label" for="inline-category-name">Category Name <span class="text-danger">*</span></label><input id="inline-category-name" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label" for="inline-category-status">Status <span class="text-danger">*</span></label><select id="inline-category-status" name="status" class="form-select" data-native-select><option value="Active" selected>Active</option><option value="Inactive">Inactive</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-tf-primary" data-inline-catalog-submit>Save Category</button></div>
        </form>
    </div></div>
</div>
@endcompanyCan

@companyCan('units.create')
<div class="modal fade" id="inlineProductUnitModal" tabindex="-1" aria-hidden="true" aria-labelledby="inlineProductUnitModalTitle">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form data-inline-catalog-form="unit">
            <div class="modal-header"><h2 class="modal-title fs-5" id="inlineProductUnitModalTitle">New Unit</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="alert alert-danger d-none" data-inline-catalog-errors role="alert"></div>
                <div class="mb-3"><label class="form-label" for="inline-unit-name">Unit Name <span class="text-danger">*</span></label><input id="inline-unit-name" name="unit_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label" for="inline-unit-type">Unit Type <span class="text-danger">*</span></label><select id="inline-unit-type" name="unit_type" class="form-select" required><option value="">Select type</option>@foreach(['Piece','Weight','Volume','Length','Area','Pack','Other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label" for="inline-unit-status">Status <span class="text-danger">*</span></label><select id="inline-unit-status" name="status" class="form-select" data-native-select><option value="Active" selected>Active</option><option value="Inactive">Inactive</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-tf-primary" data-inline-catalog-submit>Save Unit</button></div>
        </form>
    </div></div>
</div>
@endcompanyCan
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-inline-products-form]');
    if (!form || form.dataset.inlineCatalogReady === '1') return;
    form.dataset.inlineCatalogReady = '1';

    const catalogs = {
        category: [...form.querySelectorAll('[data-product-field="category_id"] option')]
            .filter((option) => option.value)
            .map((option) => ({value: option.value, text: option.text})),
        unit: [...form.querySelectorAll('[data-product-field="unit_id"] option')]
            .filter((option) => option.value)
            .map((option) => ({value: option.value, text: option.text})),
    };
    let originSelect = null;

    const fieldFor = (kind) => kind === 'category' ? 'category_id' : 'unit_id';
    const resetCatalogModal = (modalForm) => {
        modalForm.reset();
        const status = modalForm.querySelector('[name="status"]');
        if (!status) return;
        status.value = 'Active';
        window.getTradeFlowTomSelect?.(status)?.setValue('Active', true);
    };
    const hasOption = (select, value) => [...select.options].some((option) => String(option.value) === String(value));
    const updateSaveAvailability = () => {
        const ready = ['category', 'unit'].every((kind) => {
            const selects = [...form.querySelectorAll(`[data-product-field="${fieldFor(kind)}"]`)];

            return selects.length > 0 && selects.every((select) => [...select.options].some((option) => option.value));
        });

        form.querySelectorAll('[data-save-products]').forEach((button) => {
            button.disabled = !ready;
        });
    };
    const hydrateSelect = (select, kind) => {
        catalogs[kind].forEach((option) => {
            if (hasOption(select, option.value)) return;
            const control = window.getTradeFlowTomSelect?.(select);
            if (control) control.addOption(option);
            else select.add(new Option(option.text, option.value));
        });
        select.disabled = false;
        const control = window.getTradeFlowTomSelect?.(select);
        control?.enable();
        control?.refreshOptions(false);
        select.closest('[data-product-master-fields]')?.querySelector(`[data-product-catalog-empty="${kind}"]`)?.remove();
    };

    window.syncProductCatalogOptions = (root) => {
        ['category', 'unit'].forEach((kind) => {
            root.querySelectorAll?.(`[data-product-field="${fieldFor(kind)}"]`).forEach((select) => hydrateSelect(select, kind));
        });
        updateSaveAvailability();
    };

    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-inline-catalog-open]');
        if (!button) return;
        const kind = button.dataset.inlineCatalogOpen;
        const fields = button.closest('[data-product-master-fields]');
        originSelect = fields?.querySelector(`[data-product-field="${fieldFor(kind)}"]`) || null;
        const modalElement = document.getElementById(kind === 'category' ? 'inlineProductCategoryModal' : 'inlineProductUnitModal');
        const modalForm = modalElement?.querySelector('[data-inline-catalog-form]');
        if (!modalElement || !modalForm || !window.bootstrap) return;
        resetCatalogModal(modalForm);
        modalForm.querySelector('[data-inline-catalog-errors]')?.classList.add('d-none');
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        setTimeout(() => {
            resetCatalogModal(modalForm);
            modalForm.querySelector('input, select, textarea')?.focus();
        }, 150);
    });

    document.querySelectorAll('[data-inline-catalog-form]').forEach((modalForm) => {
        modalForm.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => modalForm.querySelector('[data-inline-catalog-errors]')?.classList.add('d-none'));
            field.addEventListener('change', () => modalForm.querySelector('[data-inline-catalog-errors]')?.classList.add('d-none'));
        });
        modalForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const kind = modalForm.dataset.inlineCatalogForm;
            const errors = modalForm.querySelector('[data-inline-catalog-errors]');
            const submit = modalForm.querySelector('[data-inline-catalog-submit]');
            submit.disabled = true;
            errors.classList.add('d-none');
            try {
                const response = await fetch(form.dataset[kind === 'category' ? 'inlineCategoryUrl' : 'inlineUnitUrl'], {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]')?.value || ''},
                    body: new FormData(modalForm),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw payload;

                const item = payload[kind];
                const option = {value: String(item.id), text: kind === 'category' ? item.name : item.unit_name};
                if (!catalogs[kind].some((entry) => entry.value === option.value)) catalogs[kind].push(option);
                form.querySelectorAll(`[data-product-field="${fieldFor(kind)}"]`).forEach((select) => hydrateSelect(select, kind));
                updateSaveAvailability();

                if (originSelect) {
                    const control = window.getTradeFlowTomSelect?.(originSelect);
                    if (control) control.setValue(option.value, true);
                    else {
                        originSelect.value = option.value;
                        originSelect.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                }

                window.bootstrap.Modal.getInstance(modalForm.closest('.modal'))?.hide();
                window.Swal?.fire({icon: 'success', title: payload.message || `${kind === 'category' ? 'Category' : 'Unit'} created`, timer: 1400, showConfirmButton: false});
            } catch (payload) {
                const messages = Object.values(payload.errors || {form: [payload.message || 'Unable to save this record.']}).flat();
                const duplicate = messages.some((message) => /already exists|already been taken/i.test(String(message)));
                errors.textContent = duplicate
                    ? `This ${kind} already exists for this business.`
                    : messages.join(' ');
                errors.classList.remove('d-none');
                if (duplicate) modalForm.querySelector(kind === 'category' ? '[name="name"]' : '[name="unit_type"]')?.focus();
            } finally {
                submit.disabled = false;
            }
        });
    });
});
</script>
@endpush

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
                section.querySelector('[data-product-heading]').textContent = index === 0 ? 'Product' : `Product ${index + 1}`;
                const removeButton = section.querySelector('[data-remove-product]');
                if (removeButton) removeButton.hidden = index === 0;

                section.querySelectorAll('[data-product-field], [data-product-display-field]').forEach((field) => {
                    const key = field.dataset.productField || field.dataset.productDisplayField;
                    if (field.dataset.productField) field.name = `products[${index}][${key}]`;
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
            window.syncProductCatalogOptions?.(section);
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
