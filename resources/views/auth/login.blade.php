@extends('layouts.app')
@section('title', 'Sign In | TradeFlow')
@section('content')
<section class="tf-auth-shell">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="tf-card tf-auth-card p-4 p-lg-5">
                    <a href="{{ route('public.home') }}" class="tf-brand d-flex align-items-center mb-4"><span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>TradeFlow</a>
                    <h1 class="h3 fw-bold">Sign In</h1>
                    <p class="tf-muted">Access your wholesale dashboard securely.</p>
                    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                    <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3">@csrf
                        <div><label class="form-label">Email</label><input name="email" type="email" class="form-control form-control-lg" placeholder="admin@tradeflow.com" value="{{ old('email') }}"></div>
                        <div>
                            <label class="form-label">Password</label>
                            <div class="input-group input-group-lg">
                                <input id="loginPassword" name="password" type="password" class="form-control" placeholder="Password">
                                <button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#loginPassword" data-tf-password-icon="#loginPasswordIcon"><i id="loginPasswordIcon" class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-check mb-0"><input name="remember" class="form-check-input" type="checkbox"> Remember me</label>
                            <a href="{{ route('password.request') }}">Forgot password?</a>
                        </div>
                        <button class="btn btn-tf-primary btn-lg">Sign In</button>
                        <a href="{{ route('register.business') }}" class="btn btn-outline-primary btn-lg">Register Business</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
