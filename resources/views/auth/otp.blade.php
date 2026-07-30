@extends('layouts.app')
@section('title', 'OTP Verification | TradeFlow')
@section('content')
<section class="tf-auth-shell">
    <div class="container"><div class="row justify-content-center"><div class="col-md-6 col-lg-4"><div class="tf-card p-4 text-center"><a href="{{ route('public.home') }}" class="tf-brand d-inline-flex align-items-center mb-4" aria-label="{{ $platformSettings->company_name }} home">@if($platformSettings->logo)<img src="{{ asset('storage/'.$platformSettings->logo) }}" class="tf-brand-logo" alt="">@else<span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>@endif<span>{{ $platformSettings->company_name }}</span></a><div class="tf-icon-tile bg-green text-white mx-auto mb-3"><i class="bi bi-shield-check"></i></div><h1 class="h4 fw-bold">OTP Verification</h1><p class="tf-muted">Verification screen placeholder for future OTP service.</p><div class="d-flex gap-2 justify-content-center mb-3">@for($i=0;$i<4;$i++)<input class="form-control text-center fw-bold" style="max-width:58px" maxlength="1" value="{{ $i+1 }}">@endfor</div><a href="{{ route('login') }}" class="btn btn-tf-primary w-100">Back to Login</a></div></div></div></div>
</section>
@endsection
