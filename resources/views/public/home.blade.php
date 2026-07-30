@extends('layouts.public')
@section('title', 'TradeFlow | Smart Wholesale Management Platform')
@section('content')
<section class="tf-hero tf-hero-pro">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="tf-badge tf-badge-success bg-white text-green mb-3 d-inline-block"><i class="bi bi-stars me-1"></i>Smart wholesale ERP for modern trade</span>
                <h1 class="display-3 fw-bold mb-4">Manage Your Wholesale Business Smarter</h1>
                <p class="lead mb-4 text-white-50">Connect suppliers, retailers, inventory, payments, khata, deliveries, and invoices in one powerful platform.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('register.business') }}" class="btn btn-tf-primary btn-lg"><i class="bi bi-shop me-1"></i>Start Selling</a>
                    <a href="#features" data-tf-smooth class="btn btn-light btn-lg"><i class="bi bi-grid me-1"></i>Explore Features</a>
                </div>
                <div class="row g-3 mt-5">
                    @foreach([['1.2K+','Businesses'],['28K+','Orders Tracked'],['99%','Manual Control']] as [$value,$label])
                    <div class="col-4"><div class="tf-hero-stat"><strong>{{ $value }}</strong><span>{{ $label }}</span></div></div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-5">
                <div class="tf-hero-panel">
                    <div class="d-flex justify-content-between align-items-center mb-4"><strong>Today Overview</strong><span class="tf-badge tf-badge-success">Live</span></div>
                    @foreach([['Orders','Rs 482K','bi-bag-check','bg-blue'],['Pending Khata','Rs 91K','bi-journal-text','bg-amber'],['Low Stock','12 Items','bi-exclamation-triangle','bg-red'],['Delivered','86 Orders','bi-truck','bg-green']] as [$label,$value,$icon,$color])
                    <div class="tf-mini-row"><div><i class="bi {{ $icon }} {{ $color }} text-white"></i>{{ $label }}</div><strong>{{ $value }}</strong></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tf-section bg-white">
    <div class="container">
        <div class="text-center mb-5"><h2 class="fw-bold">Trusted By Every Trade Role</h2><p class="tf-muted">Designed for the complete wholesale supply chain.</p></div>
        <div class="row g-4">
            @foreach([['Manufacturer','bi-gear-wide-connected'],['Distributor','bi-diagram-3'],['Wholesaler','bi-boxes'],['Retail Shop','bi-shop'],['Other','bi-grid-3x3-gap']] as [$type,$icon])
            <div class="col-sm-6 col-lg-4"><div class="tf-card tf-feature-card p-4 h-100"><div class="tf-icon-tile bg-blue text-white mb-3"><i class="bi {{ $icon }}"></i></div><h3 class="h5 mb-1">{{ $type }}</h3><p class="tf-muted mb-0">Role-ready workflows, clean dashboards, and secure access.</p></div></div>
            @endforeach
        </div>
    </div>
</section>

<section id="features" class="tf-section">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7"><h2 class="fw-bold">Wholesale ERP Features That Feel Fast</h2><p class="tf-muted">Manage stock, orders, khata, payments, deliveries, invoices, staff, and reports without paid external APIs.</p></div>
            <div class="col-lg-5 text-lg-end"><a href="{{ route('register.business') }}" class="btn btn-outline-primary">Register Your Business</a></div>
        </div>
        <div class="row g-4">
            @foreach([
                ['Inventory Control','Track available, sold, damaged, returned, and low stock quantities.','bi-clipboard-data'],
                ['Order Management','Create orders, update status, and generate invoices from order data.','bi-bag-check'],
                ['Khata Ledger','Maintain credit and debit history for every customer.','bi-journal-text'],
                ['Manual Payments','Record Cash, Bank Transfer, JazzCash, Easypaisa, and Cheque manually.','bi-cash-stack'],
                ['Delivery Desk','Assign delivery staff and update delivery progress without GPS dependency.','bi-truck'],
                ['Reports','Review sales, expenses, inventory, credit, and profit/loss.','bi-graph-up-arrow'],
            ] as [$title,$text,$icon])
            <div class="col-md-6 col-xl-4"><div class="tf-card tf-feature-card p-4 h-100"><div class="tf-icon-tile bg-blue text-white mb-3"><i class="bi {{ $icon }}"></i></div><h3 class="h5">{{ $title }}</h3><p class="tf-muted mb-0">{{ $text }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>

<section class="tf-section bg-white">
    <div class="container">
        <div class="text-center mb-5"><h2 class="fw-bold">How {{ $platformSettings->company_name }} Works</h2><p class="tf-muted">A practical path from registration to daily operations.</p></div>
        <div class="row g-4">
            @foreach(['Register and verify your business', 'Add products, prices, and stock', 'Receive and manage retailer orders', 'Record payments, khata, and delivery'] as $index => $step)
            <div class="col-md-3"><div class="tf-card p-4 h-100"><div class="h2 text-blue fw-bold">0{{ $index + 1 }}</div><h3 class="h6 mb-0">{{ $step }}</h3></div></div>
            @endforeach
        </div>
    </div>
</section>

<section id="pricing" class="tf-section">
    <div class="container">
        <div class="text-center mb-5"><h2 class="fw-bold">Simple plans for growing businesses</h2><p class="tf-muted">Start with a free trial after your business is approved.</p></div>
        <div class="tf-pricing-cycle mb-4" data-subscription-pricing>
            <button type="button" class="active" data-cycle="Monthly">Monthly</button>
            <button type="button" data-cycle="Yearly">Yearly</button>
        </div>
        <div class="row g-4">
            @forelse($pricingPlans as $plan)
                <div class="col-md-6 col-xl-4"><x-subscription-plan-card :plan="$plan" context="landing" :current-subscription="$currentSubscription" /></div>
            @empty
                <div class="col-12 text-center"><a href="{{ route('public.pricing') }}" class="btn btn-outline-primary">View Pricing</a></div>
            @endforelse
        </div>
        <div class="text-center mt-4"><a href="{{ route('public.pricing') }}" class="btn btn-outline-primary">Compare All Plans</a></div>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-subscription-pricing]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cycle]');
        if (!button) return;
        const yearly = button.dataset.cycle === 'Yearly';
        root.querySelectorAll('[data-cycle]').forEach((item) => item.classList.toggle('active', item === button));
        document.querySelectorAll('[data-plan-monthly-price], [data-plan-monthly-label]').forEach((item) => item.classList.toggle('d-none', yearly));
        document.querySelectorAll('[data-plan-yearly-price], [data-plan-yearly-label]').forEach((item) => item.classList.toggle('d-none', !yearly));
        document.querySelectorAll('[data-plan-cta]').forEach((link) => {
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('billing_cycle', yearly ? 'Yearly' : 'Monthly');
            link.href = url.toString();
        });
    });
});
</script>
@endpush

<section id="faq" class="tf-section bg-white">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-5"><h2 class="fw-bold">Frequently Asked Questions</h2><p class="tf-muted">Clear answers for owners before they register.</p></div>
            <div class="col-lg-7">
                <div class="accordion tf-faq" id="faqAccordion">
                    @foreach([
                        'Does '.$platformSettings->company_name.' connect directly with JazzCash or Easypaisa?' => 'No. In this MVP, JazzCash and Easypaisa are recorded manually. Businesses can save payment method, amount, date, reference number, and proof image without using paid APIs.',
                        'Can multiple businesses use the same '.$platformSettings->company_name.' platform?' => 'Yes. '.$platformSettings->company_name.' is designed as a multi-business SaaS platform where each approved business can manage its own products, orders, customers, payments, and reports separately.',
                        'Can Super Admin see business reports?' => 'Yes. Super Admin can review business-level reports for monitoring, verification, and support, but cannot directly change business products, orders, inventory, or khata records.',
                        'Can staff have limited access?' => 'Yes. Business owners can create custom roles and choose the exact business permissions each staff member receives.',
                        'Is business approval manual?' => 'Yes. New businesses submit their details and documents. Super Admin reviews them and approves, rejects, or suspends access.',
                        'Can invoices and reports be exported?' => 'Yes. '.$platformSettings->company_name.' can generate printable invoices and PDF reports using DomPDF without using any paid PDF service.',
                        'Does '.$platformSettings->company_name.' require external paid APIs?' => 'No. The MVP works with Laravel, MySQL, Blade, Bootstrap, and local/manual records only.',
                    ] as $q => $a)
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq{{ $loop->index }}">{{ $q }}</button></h3><div id="faq{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#faqAccordion"><div class="accordion-body tf-muted">{{ $a }}</div></div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section id="about" class="tf-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5"><h2 class="fw-bold">Built for Wholesale Businesses That Run on Trust</h2><p class="tf-muted">{{ $platformSettings->company_name }} helps manufacturers, distributors, wholesalers, retailers, accountants, and delivery teams manage daily operations from one secure Laravel-based platform. It is designed for local Pakistani businesses and can also support international wholesale workflows.</p></div>
            <div class="col-lg-7"><div class="row g-4">@foreach([['Business Verification','bi-shield-check'],['Manual Payment Tracking','bi-cash-stack'],['Role-Based Dashboards','bi-people']] as [$title,$icon])<div class="col-md-4"><div class="tf-card tf-feature-card p-4 h-100"><div class="tf-icon-tile bg-blue text-white mb-3"><i class="bi {{ $icon }}"></i></div><h3 class="h6">{{ $title }}</h3><p class="tf-muted mb-0">Simple, secure workflows for real wholesale teams.</p></div></div>@endforeach</div></div>
        </div>
    </div>
</section>

<section id="contact" class="tf-section">
    <div class="container">
        <div class="tf-cta-band">
            <div><h2 class="fw-bold mb-2">Ready to organize your wholesale operations?</h2><p class="mb-0 text-white-50">Register your business and submit it for approval today.</p></div>
            <div class="d-flex flex-wrap gap-3"><a href="{{ route('register.business') }}" class="btn btn-light btn-lg">Register Business</a><a href="{{ route('public.contact') }}" class="btn btn-outline-light btn-lg">Contact Sales</a></div>
        </div>
    </div>
</section>
@endsection
