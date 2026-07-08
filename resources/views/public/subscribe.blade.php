@extends('layouts.public')
@section('title', 'Start Your TradeFlow Subscription')
@section('content')
@php
    $plans = [
        'basic' => [
            'name' => 'Basic',
            'monthly' => 0,
            'products' => '100 products',
            'staff' => '3 staff',
            'orders' => '500 orders',
            'support' => 'Community support',
            'recommended' => false,
        ],
        'standard' => [
            'name' => 'Standard',
            'monthly' => 4999,
            'products' => '1,000 products',
            'staff' => '15 staff',
            'orders' => '5,000 orders',
            'support' => 'Priority business support',
            'recommended' => true,
        ],
        'premium' => [
            'name' => 'Premium',
            'monthly' => 12999,
            'products' => '10,000 products',
            'staff' => '50 staff',
            'orders' => '50,000 orders',
            'support' => 'Dedicated onboarding support',
            'recommended' => true,
        ],
    ];
    $selected = $plans[$plan];
    $yearly = round($selected['monthly'] * 12 * .85);
@endphp

<section class="tf-subscribe-hero">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <span class="tf-pill"><i class="bi bi-shield-check me-1"></i>Manual verification checkout</span>
                <h1 class="display-5 fw-bold mt-3 mb-3">Start Your TradeFlow Subscription</h1>
                <p class="lead text-white-50 mb-0">Choose your billing cycle, submit business details, and our team will manually verify and activate your SaaS workspace.</p>
            </div>
            <div class="col-lg-4">
                <div class="tf-subscribe-mini">
                    <div class="small text-white-50">Selected plan</div>
                    <div class="h3 mb-0">{{ $selected['name'] }}</div>
                    <div class="text-white-50">No online payment is charged now.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tf-section tf-section-tight">
    <div class="container">
        <div class="tf-checkout-stepper mb-4">
            @foreach([['1','Choose Plan'],['2','Business Details'],['3','Review & Submit']] as [$number, $label])
                <div class="tf-checkout-step active"><span>{{ $number }}</span><strong>{{ $label }}</strong></div>
            @endforeach
        </div>

        <div class="alert alert-success d-none tf-subscribe-success-card mb-4" data-tf-subscribe-success>
            <div class="d-flex gap-3">
                <div class="tf-icon-tile bg-green text-white"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <h2 class="h4 mb-2">Subscription Request Received</h2>
                    <p class="mb-3">Your TradeFlow subscription request has been received. Our team will contact you for verification and manual activation.</p>
                    <div class="row g-2 small">
                        <div class="col-md-3"><strong>Selected Plan</strong><div data-success-plan>{{ $selected['name'] }}</div></div>
                        <div class="col-md-3"><strong>Billing Cycle</strong><div data-success-cycle>Monthly</div></div>
                        <div class="col-md-3"><strong>Contact Phone</strong><div data-success-phone>-</div></div>
                        <div class="col-md-3"><strong>Next Step</strong><div>Business verification</div></div>
                    </div>
                </div>
            </div>
        </div>

        <form class="row g-4 align-items-start" data-tf-subscribe-form
              data-plan-name="{{ $selected['name'] }}"
              data-monthly="{{ $selected['monthly'] }}"
              data-yearly="{{ $yearly }}">
            <div class="col-lg-8">
                <div class="tf-card tf-checkout-card p-4 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <div class="tf-step-eyebrow">Step 1</div>
                            <h2 class="h4 mb-1">Choose Plan</h2>
                            <p class="tf-muted mb-0">Your selected plan is ready. You can return to pricing to compare plans.</p>
                        </div>
                        <a href="{{ route('public.home') }}#pricing" class="btn btn-outline-primary btn-sm" data-tf-smooth>Change Plan</a>
                    </div>

                    <div class="tf-selected-plan-card">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                @if($selected['recommended'])<span class="badge text-bg-success mb-2">Recommended</span>@endif
                                <h3 class="h2 fw-bold mb-2">{{ $selected['name'] }}</h3>
                                <div class="d-flex align-items-end gap-2">
                                    <span class="display-6 fw-bold">Rs {{ number_format($selected['monthly']) }}</span>
                                    <span class="tf-muted mb-2">/ month</span>
                                </div>
                                <div class="tf-muted">Yearly: Rs {{ number_format($yearly) }} <span class="text-green fw-semibold">Save 15% yearly</span></div>
                            </div>
                            <div class="row g-2 tf-plan-limits">
                                @foreach([[$selected['products'], 'bi-box'], [$selected['staff'], 'bi-people'], [$selected['orders'], 'bi-bag-check'], [$selected['support'], 'bi-headset']] as [$text, $icon])
                                    <div class="col-sm-6"><div class="tf-plan-limit"><i class="bi {{ $icon }}"></i><span>{{ $text }}</span></div></div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label fw-semibold">Billing Cycle</label>
                        <div class="tf-billing-toggle" role="group" aria-label="Billing cycle">
                            <input type="radio" class="btn-check" name="billing_cycle" id="billingMonthly" value="Monthly" checked data-billing-cycle>
                            <label class="btn" for="billingMonthly">Monthly</label>
                            <input type="radio" class="btn-check" name="billing_cycle" id="billingYearly" value="Yearly" data-billing-cycle>
                            <label class="btn" for="billingYearly">Yearly <span>Save 15%</span></label>
                        </div>
                    </div>
                </div>

                <div class="tf-card tf-checkout-card p-4">
                    <div class="mb-4">
                        <div class="tf-step-eyebrow">Step 2</div>
                        <h2 class="h4 mb-1">Business Details</h2>
                        <p class="tf-muted mb-0">These details help the TradeFlow team verify your business before manual activation.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control form-control-lg" required data-subscribe-full-name></div>
                        <div class="col-md-6"><label class="form-label">Business Name</label><input name="business_name" class="form-control form-control-lg" required></div>
                        <div class="col-md-6"><label class="form-label">Phone Number</label><input name="phone" class="form-control form-control-lg" required data-subscribe-phone></div>
                        <div class="col-md-6"><label class="form-label">Email Address</label><input name="email" type="email" class="form-control form-control-lg" required></div>
                        <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control form-control-lg" required></div>
                        <div class="col-md-6"><label class="form-label">Business Type</label><select name="business_type" class="form-select form-select-lg" required><option value="">Select type</option><option>Manufacturer</option><option>Distributor</option><option>Wholesaler</option><option>Retail Shop</option></select></div>
                        <div class="col-md-6"><label class="form-label">Selected Plan</label><input class="form-control form-control-lg" value="{{ $selected['name'] }}" readonly></div>
                        <div class="col-md-6"><label class="form-label">Preferred Payment Method</label><select name="payment_method" class="form-select form-select-lg" data-subscribe-payment><option>Cash</option><option>Bank Transfer</option><option>JazzCash Manual</option><option>Easypaisa Manual</option></select></div>
                        <div class="col-12">
                            <div class="tf-manual-note"><i class="bi bi-info-circle me-2"></i>No online payment is charged now. TradeFlow team will verify your business and manually activate your subscription.</div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-tf-primary btn-lg px-4">Submit Subscription Request</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="tf-summary-card tf-card p-4">
                    <div class="tf-step-eyebrow">Step 3</div>
                    <h2 class="h4 mb-3">Review & Submit</h2>
                    <div class="tf-summary-row"><span>Selected Plan</span><strong data-summary-plan>{{ $selected['name'] }}</strong></div>
                    <div class="tf-summary-row"><span>Billing Cycle</span><strong data-summary-cycle>Monthly</strong></div>
                    <div class="tf-summary-row"><span>Subtotal</span><strong data-summary-subtotal>Rs {{ number_format($selected['monthly']) }}</strong></div>
                    <div class="tf-summary-row"><span>Discount</span><strong class="text-green" data-summary-discount>Rs 0</strong></div>
                    <div class="tf-summary-total"><span>Total Due</span><strong data-summary-total>Rs {{ number_format($selected['monthly']) }}</strong></div>
                    <div class="tf-summary-row"><span>Payment Method</span><strong data-summary-payment>Cash</strong></div>
                    <div class="tf-summary-row"><span>Activation Type</span><strong>Manual verification</strong></div>
                    <div class="tf-summary-note mt-3">After submitting, our team will contact you to verify details and activate the subscription manually.</div>
                </div>

                <div class="row g-3 mt-3">
                    @foreach([['Manual Verification','bi-person-check'],['Secure Business Data','bi-shield-lock'],['No Paid API Required','bi-plug'],['Laravel + MySQL SaaS','bi-database-check']] as [$label, $icon])
                        <div class="col-6"><div class="tf-trust-badge"><i class="bi {{ $icon }}"></i><span>{{ $label }}</span></div></div>
                    @endforeach
                </div>
            </div>
        </form>
    </div>
</section>
@endsection
