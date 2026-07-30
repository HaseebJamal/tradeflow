@php($editingPlan = $plan ?? null)

<div class="modal-body tf-plan-modal-body">
    <div class="row g-2">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Plan Name</label>
            <input name="name" class="form-control" value="{{ old('name', $editingPlan?->name) }}" maxlength="100" required>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Active" @selected(old('status', $editingPlan?->status ?? 'Active') === 'Active')>Active</option>
                <option value="Inactive" @selected(old('status', $editingPlan?->status) === 'Inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Monthly Price</label>
            <input name="monthly_price" type="number" min="0" step="1" value="{{ old('monthly_price', $editingPlan?->priceFor('Monthly') ?? 0) }}" class="form-control" required>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Yearly Price</label>
            <input name="yearly_price" type="number" min="0" step="1" value="{{ old('yearly_price', $editingPlan?->priceFor('Yearly') ?? 0) }}" class="form-control" required>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Trial Days</label>
            <input name="trial_days" type="number" min="0" step="1" value="{{ old('trial_days', $editingPlan?->trial_days ?? 14) }}" class="form-control" required>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Sort Order</label>
            <input name="sort_order" type="number" min="0" step="1" value="{{ old('sort_order', $editingPlan?->sort_order ?? 0) }}" class="form-control">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Product Limit</label>
            <input name="product_limit" type="number" min="0" step="1" value="{{ old('product_limit', $editingPlan?->product_limit ?? 0) }}" class="form-control" required>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Staff Limit</label>
            <input name="staff_limit" type="number" min="0" step="1" value="{{ old('staff_limit', $editingPlan?->staff_limit ?? 0) }}" class="form-control" required>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Order Limit</label>
            <input name="order_limit" type="number" min="0" step="1" value="{{ old('order_limit', $editingPlan?->order_limit ?? 0) }}" class="form-control" required>
        </div>
        <div class="col-lg-3 col-md-6 d-flex align-items-end">
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_public" value="1" @checked(old('is_public', $editingPlan?->is_public ?? true))> <span class="form-check-label">Show on Public Pricing</span></label>
        </div>
        <div class="col-lg-3 col-md-6 d-flex align-items-end">
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_recommended" value="1" @checked(old('is_recommended', $editingPlan?->is_recommended ?? false))> <span class="form-check-label">Recommended Plan</span></label>
        </div>
    </div>
</div>
