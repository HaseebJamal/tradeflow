@php
    $nested = $nested ?? false;
    $index = $index ?? 0;
    $values = $values ?? [];
    $fieldName = fn (string $field) => $nested ? "products[{$index}][{$field}]" : $field;
    $errorKey = fn (string $field) => $nested ? "products.{$index}.{$field}" : $field;
    $fieldId = fn (string $field) => "product-{$index}-{$field}";
    $fieldValue = fn (string $field, mixed $default = '') => old($errorKey($field), data_get($values, $field, $default));
    $dateValue = function (string $field) use ($fieldValue): string {
        $value = $fieldValue($field);

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
    };
    $tracksBatches = (bool) $fieldValue('has_batch_tracking', false);
    $purchasePrice = data_get($values, 'latest_purchase_price')
        ?? data_get($values, 'average_purchase_price')
        ?? data_get($values, 'purchase_cost', 0);
@endphp

<div class="row g-3" data-product-master-fields>
    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldId('product_name') }}">Product Name <span class="text-danger">*</span></label>
        <input id="{{ $fieldId('product_name') }}" name="{{ $fieldName('product_name') }}" data-product-field="product_name" class="form-control @error($errorKey('product_name')) is-invalid @enderror" value="{{ $fieldValue('product_name') }}" required>
        @error($errorKey('product_name'))<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="{{ $fieldId('category_id') }}">Category <span class="text-danger">*</span></label>
        <div class="d-flex gap-2" data-product-select-control>
            <select id="{{ $fieldId('category_id') }}" name="{{ $fieldName('category_id') }}" data-product-field="category_id" class="form-select @error($errorKey('category_id')) is-invalid @enderror" required @disabled(($categories ?? collect())->isEmpty())>
                <option value="">Select category</option>
                @foreach(($categories ?? collect()) as $category)
                    <option value="{{ $category->id }}" @selected((string) $fieldValue('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @companyCan('categories.create')<button type="button" class="btn btn-sm btn-outline-primary px-2 py-1 lh-sm text-nowrap" data-inline-catalog-open="category" aria-label="Create new category">+ New</button>@endcompanyCan
        </div>
        @error($errorKey('category_id'))<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if(($categories ?? collect())->isEmpty())<div class="form-text text-danger" data-product-catalog-empty="category">Please create a category first.</div>@endif
    </div>
    <div class="col-md-3">
        <label class="form-label" for="{{ $fieldId('unit_id') }}">Unit <span class="text-danger">*</span></label>
        <div class="d-flex gap-2" data-product-select-control>
            <select id="{{ $fieldId('unit_id') }}" name="{{ $fieldName('unit_id') }}" data-product-field="unit_id" class="form-select @error($errorKey('unit_id')) is-invalid @enderror" required @disabled(($units ?? collect())->isEmpty())>
                <option value="">Select unit</option>
                @foreach(($units ?? collect()) as $unit)
                    <option value="{{ $unit->id }}" @selected((string) $fieldValue('unit_id') === (string) $unit->id)>{{ $unit->unit_name }}</option>
                @endforeach
            </select>
            @companyCan('units.create')<button type="button" class="btn btn-sm btn-outline-primary px-2 py-1 lh-sm text-nowrap" data-inline-catalog-open="unit" aria-label="Create new unit">+ New</button>@endcompanyCan
        </div>
        @error($errorKey('unit_id'))<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if(($units ?? collect())->isEmpty())<div class="form-text text-danger" data-product-catalog-empty="unit">Please create a unit first.</div>@endif
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldId('product_image') }}">Product Image</label>
        <input id="{{ $fieldId('product_image') }}" name="{{ $fieldName('product_image') }}" data-product-field="product_image" type="file" class="form-control @error($errorKey('product_image')) is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
        @error($errorKey('product_image'))<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldId('brand') }}">Brand</label>
        <input id="{{ $fieldId('brand') }}" name="{{ $fieldName('brand') }}" data-product-field="brand" class="form-control" value="{{ $fieldValue('brand') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldId('manufacturer') }}">Manufacturer</label>
        <input id="{{ $fieldId('manufacturer') }}" name="{{ $fieldName('manufacturer') }}" data-product-field="manufacturer" class="form-control" value="{{ $fieldValue('manufacturer') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldId('warehouse_location') }}">Warehouse / Location</label>
        <input id="{{ $fieldId('warehouse_location') }}" name="{{ $fieldName('warehouse_location') }}" data-product-field="warehouse_location" class="form-control" value="{{ $fieldValue('warehouse_location') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="{{ $fieldId('status') }}">Status <span class="text-danger">*</span></label>
        <select id="{{ $fieldId('status') }}" name="{{ $fieldName('status') }}" data-product-field="status" class="form-select @error($errorKey('status')) is-invalid @enderror" required>
            <option value="Active" @selected($fieldValue('status', 'Active') === 'Active')>Active</option>
            <option value="Inactive" @selected($fieldValue('status') === 'Inactive')>Inactive</option>
        </select>
        @error($errorKey('status'))<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check mb-2">
            <input id="{{ $fieldId('has_batch_tracking') }}" name="{{ $fieldName('has_batch_tracking') }}" data-product-field="has_batch_tracking" class="form-check-input" type="checkbox" value="1" @checked($tracksBatches) data-product-batch-toggle>
            <label class="form-check-label" for="{{ $fieldId('has_batch_tracking') }}">Batch / expiry tracking</label>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldId('retail_price') }}">Retail Selling Price</label>
        <input id="{{ $fieldId('retail_price') }}" name="{{ $fieldName('retail_price') }}" data-product-field="retail_price" type="number" min="0" step="any" data-allow-decimal class="form-control @error($errorKey('retail_price')) is-invalid @enderror" value="{{ $fieldValue('retail_price', 0) }}">
        @error($errorKey('retail_price'))<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldId('wholesale_price') }}">Wholesale Selling Price</label>
        <input id="{{ $fieldId('wholesale_price') }}" name="{{ $fieldName('wholesale_price') }}" data-product-field="wholesale_price" type="number" min="0" step="any" data-allow-decimal class="form-control @error($errorKey('wholesale_price')) is-invalid @enderror" value="{{ $fieldValue('wholesale_price', 0) }}">
        @error($errorKey('wholesale_price'))<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldId('purchase_price') }}">Purchase Price</label>
        <input id="{{ $fieldId('purchase_price') }}" data-product-display-field="purchase_price" type="text" class="form-control" value="Rs {{ number_format((float) $purchasePrice, 2) }}" readonly aria-describedby="{{ $fieldId('purchase_price') }}-help">
        <div id="{{ $fieldId('purchase_price') }}-help" class="form-text">Updated from accepted goods receipts only.</div>
    </div>

    <div class="row g-3 mx-0 {{ $tracksBatches ? '' : 'd-none' }}" data-product-batch-fields>
        <div class="col-md-3">
            <label class="form-label" for="{{ $fieldId('batch_number') }}">Batch Number</label>
            <input id="{{ $fieldId('batch_number') }}" name="{{ $fieldName('batch_number') }}" data-product-field="batch_number" class="form-control" value="{{ $fieldValue('batch_number') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="{{ $fieldId('manufacturing_date') }}">Manufacturing Date</label>
            <input id="{{ $fieldId('manufacturing_date') }}" name="{{ $fieldName('manufacturing_date') }}" data-product-field="manufacturing_date" type="date" class="form-control" value="{{ $dateValue('manufacturing_date') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="{{ $fieldId('expiry_date') }}">Expiry Date</label>
            <input id="{{ $fieldId('expiry_date') }}" name="{{ $fieldName('expiry_date') }}" data-product-field="expiry_date" type="date" class="form-control" value="{{ $dateValue('expiry_date') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="{{ $fieldId('expiry_alert_days') }}">Expiry Alert Days</label>
            <input id="{{ $fieldId('expiry_alert_days') }}" name="{{ $fieldName('expiry_alert_days') }}" data-product-field="expiry_alert_days" type="number" min="0" step="1" class="form-control" value="{{ $fieldValue('expiry_alert_days') }}">
        </div>
    </div>

</div>
