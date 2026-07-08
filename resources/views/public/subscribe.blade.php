@extends('layouts.public')
@section('title', 'Start Your TradeFlow Subscription')
@section('content')
@php
    $plans = [
        'basic' => ['Basic','Rs 0','100 products','3 staff','500 orders'],
        'standard' => ['Standard','Rs 4,999','1,000 products','15 staff','5,000 orders'],
        'premium' => ['Premium','Rs 12,999','10,000 products','50 staff','50,000 orders'],
    ];
    [$name,$price,$products,$staff,$orders] = $plans[$plan];
@endphp
<section class="tf-section" style="padding-top:140px">
    <div class="container">
        <div class="text-center mb-5"><h1 class="fw-bold">Start Your TradeFlow Subscription</h1><p class="tf-muted">Submit your request. No online checkout or payment API is connected.</p></div>
        <div class="row g-4">
            <div class="col-lg-4"><div class="tf-card tf-price-card p-4"><h2 class="h4">{{ $name }}</h2><div class="display-5 fw-bold my-3">{{ $price }}</div><p><i class="bi bi-check-circle-fill text-green me-2"></i>{{ $products }}</p><p><i class="bi bi-check-circle-fill text-green me-2"></i>{{ $staff }}</p><p><i class="bi bi-check-circle-fill text-green me-2"></i>{{ $orders }}</p></div></div>
            <div class="col-lg-8"><div class="tf-card p-4">@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif<div class="alert alert-success d-none" data-tf-subscribe-success>Your subscription request has been received. Our team will contact you for manual verification and activation.</div><form class="row g-3" data-tf-subscribe-form><div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" required></div><div class="col-md-6"><label class="form-label">Business Name</label><input class="form-control" required></div><div class="col-md-6"><label class="form-label">Phone Number</label><input class="form-control" required></div><div class="col-md-6"><label class="form-label">Email Address</label><input type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label">City</label><input class="form-control" required></div><div class="col-md-6"><label class="form-label">Selected Plan</label><input class="form-control" value="{{ $name }}" readonly></div><div class="col-md-6"><label class="form-label">Billing Cycle</label><select class="form-select"><option>Monthly</option><option>Yearly</option></select></div><div class="col-md-6"><label class="form-label">Preferred Payment Method</label><select class="form-select"><option>Cash</option><option>Bank Transfer</option><option>JazzCash Manual</option><option>Easypaisa Manual</option></select></div><div class="col-12"><p class="tf-muted small mb-0">After submitting, TradeFlow team will contact you to verify your business and manually activate your subscription.</p></div><div class="col-12"><button class="btn btn-tf-primary btn-lg">Submit Subscription Request</button></div></form></div></div>
        </div>
    </div>
</section>
@endsection
