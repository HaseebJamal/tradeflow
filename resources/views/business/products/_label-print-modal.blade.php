<div class="modal fade" id="productLabelModal" tabindex="-1" aria-labelledby="productLabelModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable tf-label-config-dialog">
        <form class="modal-content" method="POST" action="{{ route('business.products.labels.preview') }}" target="_blank" data-label-print-form>
            @csrf
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-1" id="productLabelModalTitle">Print barcode labels</h2>
                    <p class="tf-muted small mb-0">Choose the label details and quantity for each selected product.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-none small py-2" role="alert" data-label-print-message></div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Label format</label>
                        <div class="tf-label-format-options">
                            <input type="hidden" name="format" value="thermal" data-label-format-input>
                            <button class="btn btn-tf-primary" type="button" data-label-format-option="thermal" aria-pressed="true"><i class="bi bi-receipt-cutoff me-1"></i>Thermal</button>
                            <button class="btn btn-outline-primary" type="button" data-label-format-option="a4" aria-pressed="false"><i class="bi bi-file-earmark-text me-1"></i>A4 Sheet</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="labelPriceType">Price to show</label>
                        <select id="labelPriceType" name="price_type" class="form-select" data-label-price-type>
                            <option value="retail">Retail price</option>
                            <option value="wholesale">Wholesale price</option>
                            <option value="none">No price</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 mb-3 small">
                    <input type="hidden" name="show_business_name" value="0">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="show_business_name" value="1" checked><span class="form-check-label">Business name</span></label>
                    <input type="hidden" name="show_product_name" value="0">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="show_product_name" value="1" checked><span class="form-check-label">Product name</span></label>
                    <input type="hidden" name="show_sku" value="0">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="show_sku" value="1"><span class="form-check-label">SKU</span></label>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h3 class="h6 mb-0">Selected products</h3>
                    <span class="small tf-muted" data-label-selection-summary>0 products · 0 labels</span>
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0 tf-label-selection-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Barcode</th>
                                <th>Retail</th>
                                <th>Wholesale</th>
                                <th class="tf-label-quantity-heading">Print qty</th>
                                <th><span class="visually-hidden">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody data-label-selection-rows></tbody>
                    </table>
                </div>
                <p class="small tf-muted mb-0 mt-2">Each label uses the product's existing barcode and saved selling price. Maximum 500 labels per product and 2,000 labels per preview.</p>
                <div data-label-hidden-fields></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-tf-primary"><i class="bi bi-eye me-1"></i>Open Preview</button>
            </div>
        </form>
    </div>
</div>
