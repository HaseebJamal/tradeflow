@extends('layouts.dashboard')
@section('page-title', 'Settings')
@section('page-subtitle', 'Business and account settings')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4 mb-4">
    <h2 class="h5 mb-1">Protected Business Details</h2>
    <p class="tf-muted mb-3">Only Super Admin can apply changes to business name, phone, address, city, category, and logo.</p>
    <div class="row g-3">
        @foreach(['Business Name' => $business?->business_name, 'Phone' => $business?->phone, 'Address' => $business?->address, 'City' => $business?->city, 'Category' => $business?->category] as $label => $value)
            <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $label }}</small><strong>{{ $value ?: '—' }}</strong></div></div>
        @endforeach
        <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Logo</small>@if($business?->logo)<img src="{{ asset('storage/'.$business->logo) }}" class="navbar-avatar mt-1" alt="{{ $business->business_name }} logo">@else<strong>Not uploaded</strong>@endif</div></div>
    </div>
</div>

<div class="tf-card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h5 mb-1">Request Business Details Change</h2><p class="tf-muted mb-0">Your request is sent to Super Admin and is applied only after approval.</p></div>
        @if($pendingRequest)<span class="tf-badge tf-badge-warning">Pending review</span>@endif
    </div>
    @if(auth()->user()->role === 'business_owner')
    <form method="POST" action="{{ route('business.settings.business') }}" enctype="multipart/form-data" class="row g-3" data-tf-tab-order>@csrf @method('PUT')
        <div class="col-md-6"><label class="form-label">Requested Business Name</label><input name="business_name" class="form-control" value="{{ old('business_name', $pendingRequest?->requested_values['business_name'] ?? $business?->business_name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Requested Phone</label><input name="phone" class="form-control" value="{{ old('phone', $pendingRequest?->requested_values['phone'] ?? $business?->phone) }}" required></div>
        <div class="col-md-12"><label class="form-label">Requested Address</label><input name="address" class="form-control" value="{{ old('address', $pendingRequest?->requested_values['address'] ?? $business?->address) }}" required></div>
        <div class="col-md-4"><label class="form-label">Requested City</label><input name="city" class="form-control" value="{{ old('city', $pendingRequest?->requested_values['city'] ?? $business?->city) }}" required></div>
        <div class="col-md-4"><label class="form-label">Requested Category <span class="tf-muted small">Optional</span></label><input name="category" class="form-control" value="{{ old('category', $pendingRequest?->requested_values['category'] ?? $business?->category) }}"></div>
        <div class="col-md-4"><label class="form-label">Requested Logo <span class="tf-muted small">Optional</span></label><input name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control"></div>
        <div class="col-12"><label class="form-label">Reason for Change</label><textarea name="reason" class="form-control" rows="3" minlength="10" maxlength="2000" required placeholder="Explain why this change is needed.">{{ old('reason', $pendingRequest?->reason) }}</textarea></div>
        <div class="col-12"><button class="btn btn-tf-primary">Submit Change Request</button></div>
    </form>
    @else
        <div class="alert alert-info mb-0">Use the Super Admin Companies area to apply protected business-detail changes.</div>
    @endif
</div>
@endsection
