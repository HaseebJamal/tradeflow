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
                    @foreach([['Orders','Rs 482K','bi-bag-check','bg-blue'],['Pending Khata','Rs 91K','bi-journal-text','bg-amber'],['Low Stock','12 SKUs','bi-exclamation-triangle','bg-red'],['Delivered','86 Orders','bi-truck','bg-green']] as [$label,$value,$icon,$color])
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
            @foreach([['Manufacturer','bi-gear-wide-connected'],['Distributor','bi-diagram-3'],['Wholesaler','bi-boxes'],['Retail Shop','bi-shop'],['Delivery Staff','bi-truck'],['Accountant','bi-calculator']] as [$type,$icon])
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
        <div class="text-center mb-5"><h2 class="fw-bold">How TradeFlow Works</h2><p class="tf-muted">A practical path from registration to daily operations.</p></div>
        <div class="row g-4">
            @foreach(['Register and verify your business', 'Add products, prices, and stock', 'Receive and manage retailer orders', 'Record payments, khata, and delivery'] as $index => $step)
            <div class="col-md-3"><div class="tf-card p-4 h-100"><div class="h2 text-blue fw-bold">0{{ $index + 1 }}</div><h3 class="h6 mb-0">{{ $step }}</h3></div></div>
            @endforeach
        </div>
    </div>
</section>

<section id="pricing" class="tf-section">
    <div class="container">
        <div class="text-center mb-5"><h2 class="fw-bold">Simple Manual Plans</h2><p class="tf-muted">Super admin activates subscriptions manually. No payment gateway required.</p></div>
        <div class="row g-4">
            @foreach([['Basic','Rs 0','For small shops getting started',['100 products','3 staff','500 orders']],['Standard','Rs 4,999','For growing distributors',['1,000 products','15 staff','5,000 orders']],['Premium','Rs 12,999','For scaled wholesale teams',['10,000 products','50 staff','50,000 orders']]] as [$plan,$price,$desc,$items])
            <div class="col-md-4"><div class="tf-card tf-price-card p-4 h-100"><h3 class="h4">{{ $plan }}</h3><div class="display-5 fw-bold my-3">{{ $price }}</div><p class="tf-muted">{{ $desc }}</p>@foreach($items as $item)<p><i class="bi bi-check-circle-fill text-green me-2"></i>{{ $item }}</p>@endforeach<a href="{{ route('subscribe.plan', strtolower($plan)) }}" class="btn btn-tf-primary w-100 mt-2">Choose {{ $plan }}</a></div></div>
            @endforeach
        </div>
    </div>
</section>

<section id="faq" class="tf-section bg-white">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-5"><h2 class="fw-bold">Frequently Asked Questions</h2><p class="tf-muted">Clear answers for owners before they register.</p></div>
            <div class="col-lg-7">
                <div class="accordion tf-faq" id="faqAccordion">
                    @foreach([
                        'Does TradeFlow connect directly with JazzCash or Easypaisa?' => 'No. In this MVP, JazzCash and Easypaisa are recorded manually. Businesses can save payment method, amount, date, reference number, and proof image without using paid APIs.',
                        'Can multiple businesses use the same TradeFlow platform?' => 'Yes. TradeFlow is designed as a multi-business SaaS platform where each approved business can manage its own products, orders, customers, payments, and reports separately.',
                        'Can Super Admin see business reports?' => 'Yes. Super Admin can review business-level reports for monitoring, verification, and support, but cannot directly change business products, orders, inventory, or khata records.',
                        'Can staff have limited access?' => 'Yes. Business owners can create staff accounts and assign roles such as manager, sales staff, inventory staff, accountant, and delivery staff.',
                        'Is business approval manual?' => 'Yes. New businesses submit their details and documents. Super Admin reviews them and approves, rejects, or suspends access.',
                        'Can invoices and reports be exported?' => 'Yes. TradeFlow can generate printable invoices and PDF reports using DomPDF without using any paid PDF service.',
                        'Does TradeFlow require external paid APIs?' => 'No. The MVP works with Laravel, MySQL, Blade, Bootstrap, and local/manual records only.',
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
            <div class="col-lg-5"><h2 class="fw-bold">Built for Wholesale Businesses That Run on Trust</h2><p class="tf-muted">TradeFlow helps manufacturers, distributors, wholesalers, retailers, accountants, and delivery teams manage daily operations from one secure Laravel-based platform. It is designed for local Pakistani businesses and can also support international wholesale workflows.</p></div>
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
