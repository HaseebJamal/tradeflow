@extends('layouts.public')
@section('title', 'Privacy & Security | TradeFlow')
@section('content')
<section class="tf-section" style="padding-top:140px">
    <div class="container">
        <div class="mb-5"><span class="tf-badge tf-badge-info">Trust Center</span><h1 class="fw-bold mt-3">Privacy & Security</h1><p class="tf-muted">{{ $platformSettings->company_name }} is built for secure multi-business wholesale operations with local/manual records.</p></div>
        <div class="row g-4">
            @foreach([
                ['Data Privacy','Business information is stored inside the Laravel and MySQL application database configured for your deployment.'],
                ['Business Data Isolation','Each approved business can only access its own products, customers, orders, payments, khata, and reports.'],
                ['Role-Based Access','Owners can assign staff roles and permissions for limited module access.'],
                ['Secure File Uploads','Uploaded images and documents are validated by file type and size before storage.'],
                ['Password Protection','Passwords are hashed using Laravel authentication security.'],
                ['Manual Payment Records Only','Payments are stored as manual records with method, amount, date, reference number, and proof image.'],
                ['No Paid API Dependency',$platformSettings->company_name.' does not connect to JazzCash, Easypaisa, WhatsApp, SMS, Google Maps, or payment gateway APIs in this MVP.'],
            ] as [$title,$text])
            <div class="col-md-6 col-xl-4"><div class="tf-card p-4 h-100"><h2 class="h5">{{ $title }}</h2><p class="tf-muted mb-0">{{ $text }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
