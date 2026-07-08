@extends('layouts.app')
@section('title', 'Forgot Password | TradeFlow')
@section('content')
<section class="tf-auth-shell">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="tf-card tf-auth-card p-4 p-lg-5">
                    <a href="{{ route('public.home') }}" class="tf-brand d-flex align-items-center mb-4"><span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>TradeFlow</a>
                    <h1 class="h3 fw-bold">Forgot Password</h1>
                    <p class="tf-muted">Enter your email. This screen is frontend-only and does not send email.</p>
                    <div class="alert alert-info d-none" data-tf-reset-message>Reset link placeholder shown. No email API is integrated.</div>
                    <form class="d-grid gap-3" data-tf-forgot-form>
                        <div><label class="form-label">Email</label><input type="email" class="form-control form-control-lg" placeholder="name@example.com" required></div>
                        <button class="btn btn-tf-primary btn-lg">Send Reset Link</button>
                        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg">Back to Sign In</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
