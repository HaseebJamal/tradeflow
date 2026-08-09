@extends('layouts.dashboard')
@section('title', 'Business Profile | TradeFlow')
@section('page-title', 'Business Profile')
@section('page-subtitle', 'Profile information for the business you are viewing')
@section('content')
@php($hasLogo = $business->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($business->logo))
<div class="row g-4">
    <div class="col-lg-7">
        <div class="tf-card p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                @if($hasLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($business->logo) }}?v={{ $business->updated_at?->timestamp }}" class="profile-avatar" alt="{{ $business->business_name }} logo">
                @else
                    <span class="profile-avatar tf-avatar-empty"><i class="bi bi-building"></i></span>
                @endif
                <div><h2 class="h5 mb-1">{{ $business->business_name }}</h2><span class="tf-muted">{{ $business->business_type ?: 'Business' }}</span></div>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $business->phone ?: '—' }}</dd>
                <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $business->address ?: '—' }}</dd>
                <dt class="col-sm-4">City</dt><dd class="col-sm-8">{{ $business->city ?: '—' }}</dd>
                <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $business->category ?: '—' }}</dd>
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $business->status }}</dd>
            </dl>
        </div>
    </div>
    <div class="col-lg-5"><div class="tf-card p-4"><h2 class="h5">Business Owner</h2><p class="mb-1 fw-semibold">{{ $business->owner?->name ?: 'Not assigned' }}</p><p class="tf-muted mb-0">{{ $business->owner?->email ?: 'No owner account is assigned.' }}</p></div></div>
</div>
@endsection
