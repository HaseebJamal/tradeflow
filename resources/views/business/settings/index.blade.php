@extends('layouts.dashboard')
@section('page-title', 'Settings')
@section('page-subtitle', 'Business and account settings')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4">
    <h2 class="h5">Business Settings</h2>
    <form method="POST" action="{{ route('business.settings.business') }}" enctype="multipart/form-data" class="row g-3">@csrf @method('PUT')
        <div class="col-md-6"><label class="form-label">Business Name</label><input name="business_name" class="form-control" value="{{ old('business_name', $business?->business_name) }}"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $business?->phone) }}"></div>
        <div class="col-md-12"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $business?->address) }}"></div>
        <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $business?->city) }}"></div>
        <div class="col-md-4"><label class="form-label">Category</label><input name="category" class="form-control" value="{{ old('category', $business?->category) }}"></div>
        <div class="col-md-4"><label class="form-label">Logo</label><input name="logo" type="file" class="form-control"></div>
        <div class="col-12"><button class="btn btn-tf-primary">Save Settings</button></div>
    </form>
</div>
@endsection
