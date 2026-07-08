@extends('layouts.public')
@section('title', 'Pricing | TradeFlow')
@section('content')
<section class="tf-section" style="padding-top:140px">
    <div class="container">
        <div class="text-center mb-5"><h1 class="fw-bold">Simple Pricing Preview</h1><p class="tf-muted">Static UI only. No billing integration.</p></div>
        <div class="row g-4">
            @foreach([['Starter','Rs 0',['Catalog preview','Basic order list','Retailer browsing']],['Growth','Rs 4,999',['Inventory dashboard','Khata ledger','Invoices and staff']],['Scale','Rs 12,999',['Multi-role UI','Advanced reports','Priority ticket screen']]] as [$plan,$price,$features])
            <div class="col-md-4"><div class="tf-card p-4 h-100"><h2 class="h4">{{ $plan }}</h2><div class="display-5 fw-bold mb-3">{{ $price }}</div>@foreach($features as $item)<p><i class="bi bi-check2 text-green me-2"></i>{{ $item }}</p>@endforeach<a href="{{ route('register.business') }}" class="btn btn-tf-primary w-100 mt-3">Choose Plan</a></div></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
