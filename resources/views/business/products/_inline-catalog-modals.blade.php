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
                <div class="mb-3"><label class="form-label" for="inline-unit-type">Unit Type <span class="text-danger">*</span></label><select id="inline-unit-type" name="unit_type" class="form-select" data-native-select required><option value="">Select type</option>@foreach(['Piece','Weight','Volume','Length','Area','Pack','Other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label" for="inline-unit-status">Status <span class="text-danger">*</span></label><select id="inline-unit-status" name="status" class="form-select" data-native-select><option value="Active" selected>Active</option><option value="Inactive">Inactive</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-tf-primary" data-inline-catalog-submit>Save Unit</button></div>
        </form>
    </div></div>
</div>
@endcompanyCan
