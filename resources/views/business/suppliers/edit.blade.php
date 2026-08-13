@extends('layouts.dashboard')
@section('page-title', 'Edit Supplier')
@section('page-subtitle', $supplier->supplier_name)
@section('content')
@if($errors->any())
    <div class="alert alert-danger" @if($errors->first('supplier_name') === 'A supplier with the same phone or complete identity already exists for this business.') data-tf-alert-title="Supplier already exists" @endif>{{ $errors->first() }}</div>
@endif
<div class="tf-card tf-suppliers-form-card p-4">
    <form method="POST" action="{{ route('business.suppliers.update', $supplier) }}" class="row g-3">
        @csrf @method('PUT')
        <div class="col-md-4"><label class="form-label">Supplier Name</label><div class="tf-identity-input-wrap"><input name="supplier_name" value="{{ $supplier->supplier_name }}" class="form-control tf-identity-input" readonly required><i class="bi bi-lock-fill" aria-hidden="true"></i></div><div class="form-text tf-identity-helper">Supplier name cannot be changed after creation.</div></div>
        <div class="col-md-4"><label class="form-label">Company Name</label><div class="tf-identity-input-wrap"><input name="company_name" value="{{ $supplier->company_name }}" class="form-control tf-identity-input" readonly aria-describedby="supplier-company-name-help"><i class="bi bi-lock-fill" aria-hidden="true"></i></div><div id="supplier-company-name-help" class="form-text tf-identity-helper">Company name cannot be changed after creation.</div></div>
        <div class="col-md-4"><label class="form-label">Phone</label><x-phone-input name="phone" :value="old('phone', $supplier->phone)" :error="$errors->first('phone')" /></div>
        <div class="col-md-4"><label class="form-label">Email</label><input name="email" type="email" value="{{ old('email', $supplier->email) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">City</label><input name="city" value="{{ old('city', $supplier->city) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Opening Balance</label><input name="opening_balance" type="number" step="1" min="0" value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}" class="form-control js-whole-number"><small class="text-muted">Optional - defaults to Rs 0</small></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option @selected(old('status', $supplier->status) === 'Active')>Active</option><option @selected(old('status', $supplier->status) === 'Inactive')>Inactive</option></select></div>
        <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="3">{{ old('address', $supplier->address) }}</textarea></div>
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-tf-primary">Update Supplier</button><a href="{{ route('business.suppliers.show', $supplier) }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div>
@endsection
