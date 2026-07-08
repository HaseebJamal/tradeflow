@extends('layouts.public')
@section('title', 'Contact | TradeFlow')
@section('content')
<section class="tf-section" style="padding-top:140px">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5"><h1 class="fw-bold">Contact TradeFlow</h1><p class="tf-muted">Send an inquiry directly into the support ticket queue.</p><div class="tf-card p-4"><p><i class="bi bi-envelope text-blue me-2"></i>hello@tradeflow.test</p><p><i class="bi bi-telephone text-blue me-2"></i>+92 300 0000000</p><p class="mb-0"><i class="bi bi-geo-alt text-blue me-2"></i>Lahore, Pakistan</p></div></div>
            <div class="col-lg-7"><div class="tf-card p-4">@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif<form method="POST" action="{{ route('contact.store') }}">@csrf<div class="row g-3"><div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" placeholder="Your name"></div><div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" placeholder="+92"></div><div class="col-12"><label class="form-label">Email</label><input name="email" class="form-control" placeholder="name@example.com"></div><div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="5" placeholder="How can we help?"></textarea></div><div class="col-12"><button class="btn btn-tf-primary">Send Message</button></div></div></form></div></div>
        </div>
    </div>
</section>
@endsection
