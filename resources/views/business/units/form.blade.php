@extends('layouts.dashboard')
@section('page-title', isset($unit) ? 'Edit Unit' : 'Add Unit')
@section('page-subtitle', 'Create a product measurement unit for this business')
@section('content')
<div class="tf-card p-4" style="max-width:760px">
    <form method="POST" action="{{ isset($unit) ? route('business.units.update', $unit) : route('business.units.store') }}" class="row g-3">
        @csrf
        @isset($unit)@method('PUT')@endisset
        <div class="col-12"><label class="form-label">Unit Name <span class="text-danger">*</span></label><input name="unit_name" class="form-control @error('unit_name') is-invalid @enderror @error('unit_name_normalized') is-invalid @enderror" value="{{ old('unit_name', $unit->unit_name ?? '') }}" required autofocus>@error('unit_name')<div class="invalid-feedback">{{ $message }}</div>@enderror @error('unit_name_normalized')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Unit Type <span class="text-danger">*</span></label><select name="unit_type" class="form-select @error('unit_type') is-invalid @enderror" required><option value="">Select type</option>@foreach(['Piece','Weight','Volume','Length','Area','Pack','Other'] as $type)<option value="{{ $type }}" @selected(old('unit_type', $unit->unit_type ?? '') === $type)>{{ $type }}</option>@endforeach</select>@error('unit_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Status <span class="text-danger">*</span></label><select name="status" class="form-select"><option value="Active" @selected(old('status', $unit->status ?? 'Active') === 'Active')>Active</option><option value="Inactive" @selected(old('status', $unit->status ?? '') === 'Inactive')>Inactive</option></select></div>
        <div class="col-12"><label class="form-label">Description <span class="text-muted">(Optional)</span></label><textarea name="description" rows="3" class="form-control">{{ old('description', $unit->description ?? '') }}</textarea></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-tf-primary">{{ isset($unit) ? 'Update Unit' : 'Save Unit' }}</button><a class="btn btn-outline-secondary" href="{{ route('business.units.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
