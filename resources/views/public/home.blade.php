@extends('layouts.public')
@section('title', 'Profit Point | Wholesale Management, made effortless')

@section('content')
    @php
        $publicDemos = collect(['en' => 'English', 'ur' => 'اردو'])->mapWithKeys(function (string $label, string $locale) use ($platformSettings) {
            $prefix = 'demo_'.$locale.'_';
            $source = (string) $platformSettings->getAttribute($prefix.'video_type');
            $value = trim((string) $platformSettings->getAttribute($prefix.'video_url'));
            $path = preg_replace('#^(?:public/|storage/)#', '', ltrim($value, '/'));
            $uploaded = $source === 'upload' && filled($path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
            $external = $source === 'external' && str_starts_with($value, 'https://') && in_array(strtolower(pathinfo((string) parse_url($value, PHP_URL_PATH), PATHINFO_EXTENSION)), ['mp4', 'webm', 'ogv'], true);
            if (! $platformSettings->getAttribute($prefix.'is_active') || ! ($uploaded || $external)) return [];
            $posterPath = preg_replace('#^(?:public/|storage/)#', '', ltrim((string) $platformSettings->getAttribute($prefix.'poster'), '/'));
            return [$locale => ['label' => $label, 'title' => $platformSettings->getAttribute($prefix.'title') ?: ($locale === 'en' ? 'See Profit Point in action' : 'پرافٹ پوائنٹ دیکھیں'), 'subtitle' => $platformSettings->getAttribute($prefix.'subtitle'), 'url' => $uploaded ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : $value, 'poster' => filled($posterPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($posterPath) ? \Illuminate\Support\Facades\Storage::disk('public')->url($posterPath) : null]];
        });
        $hasPublicDemo = $publicDemos->isNotEmpty();
        $whatsAppDigits = preg_replace('/\D+/', '', (string) ($platformSettings->whatsapp_number ?? ''));
        $hasPublicWhatsApp = (bool) $platformSettings->whatsapp_is_active && preg_match('/^[1-9]\d{7,14}$/', $whatsAppDigits);
        $whatsAppUrl = $hasPublicWhatsApp ? 'https://wa.me/' . $whatsAppDigits . (filled($platformSettings->whatsapp_message) ? '?text=' . rawurlencode($platformSettings->whatsapp_message) : '') : null;
    @endphp
    <section class="pp-hero" id="hero" data-parallax-root>
        <div class="pp-orb pp-orb-one" data-parallax="0.12"></div>
        <div class="pp-orb pp-orb-two" data-parallax="-0.08"></div>
        <div class="pp-grid-glow"></div>
        <div class="container position-relative">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 pp-hero-copy" data-reveal>
                    <span class="pp-eyebrow"><span class="pp-live-dot"></span>Built for ambitious wholesale teams</span>
                    <h1>One clear view of <span>every moving part.</span></h1>
                    <p class="pp-hero-lead">Profit Point gives wholesale businesses a calmer way to run inventory, orders,
                        payments, deliveries, and customer credit—all from one beautifully simple workspace.</p>
                    <div class="d-flex flex-wrap gap-3 pp-hero-actions">
                        <a href="{{ route('register.business') }}" class="btn pp-btn-primary btn-lg">Start free trial <i
                                class="bi bi-arrow-up-right"></i></a>
                        <!-- <a href="#platform" data-tf-smooth class="btn pp-btn-secondary btn-lg"><i class="bi bi-play-circle"></i> Explore platform</a> -->
                        @if($hasPublicDemo)<button type="button" class="btn pp-btn-secondary pp-btn-demo btn-lg"
                            data-bs-toggle="modal" data-bs-target="#profitPointDemoModal"><i class="bi bi-play-fill"></i>
                        Watch demo</button>@endif
                    </div>
                    <div class="pp-trust-row">
                        <div class="pp-avatars"><span>SA</span><span>MK</span><span>RH</span><span>+</span></div>
                        <div><strong>Trusted by 1,200+ teams</strong><small><i class="bi bi-stars"></i> Built for the real
                                world of trade</small></div>
                    </div>
                </div>
                <div class="col-lg-6" data-reveal data-reveal-delay="120">
                    <div class="pp-hero-visual" aria-label="Profit Point dashboard preview">
                        <div class="pp-floating-card pp-float-revenue"><span><i class="bi bi-graph-up-arrow"></i> Revenue
                                today</span><strong>Rs 482,600</strong><em><i class="bi bi-arrow-up"></i> 18.4%</em></div>
                        <div class="pp-floating-card pp-float-stock"><span class="pp-icon-warning"><i
                                    class="bi bi-box-seam"></i></span>
                            <div><small>Low stock alert</small><strong>12 items</strong></div>
                        </div>
                        <div class="pp-dashboard pp-dashboard-hero">
                            <aside>
                                <div class="pp-mini-logo"><i class="bi bi-boxes"></i></div><span class="is-active"><i
                                        class="bi bi-grid-1x2"></i></span><span><i
                                        class="bi bi-bag-check"></i></span><span><i class="bi bi-box"></i></span><span><i
                                        class="bi bi-people"></i></span><span><i class="bi bi-gear"></i></span>
                            </aside>
                            <div class="pp-dash-main">
                                <div class="pp-dash-top">
                                    <div><small>Thursday, 7 August</small><strong>Good morning, Ayesha
                                            <span>✦</span></strong></div>
                                    <div class="pp-top-actions"><i class="bi bi-bell"></i><span>AM</span></div>
                                </div>
                                <div class="pp-dash-stat-grid">
                                    <div><span>Net sales</span><strong>Rs 482K</strong><em class="is-up">+18.4%</em></div>
                                    <div><span>Orders</span><strong>184</strong><em class="is-up">+12.8%</em></div>
                                    <div><span>To collect</span><strong>Rs 91K</strong><em class="is-down">-4.2%</em></div>
                                </div>
                                <div class="pp-dash-chart">
                                    <div class="pp-chart-title">
                                        <div><strong>Sales overview</strong><span>Last 7 days</span></div><button>Weekly <i
                                                class="bi bi-chevron-down"></i></button>
                                    </div>
                                    <div class="pp-bars"><i style="height:35%"></i><i style="height:52%"></i><i
                                            style="height:43%"></i><i style="height:76%"></i><i style="height:57%"></i><i
                                            style="height:88%"></i><i style="height:66%"></i></div>
                                    <div class="pp-chart-days">
                                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pp-proof-strip" data-reveal><span>Everything you need to stay in control</span>
                <div><i class="bi bi-shield-check"></i> Bank-level security</div>
                <div><i class="bi bi-lightning-charge"></i> Set up in minutes</div>
                <div><i class="bi bi-headset"></i> Local support</div>
            </div>
        </div>
    </section>

    <section class="pp-logo-section" aria-label="Trusted businesses">
        <div class="container">
            <p>Powering the next generation of wholesale businesses</p>
            <div class="pp-logo-row"><span><i class="bi bi-buildings"></i> NORTHSTAR</span><span><i
                        class="bi bi-hexagon"></i> APEX TRADE</span><span><i class="bi bi-layers"></i>
                    MERIDIAN</span><span><i class="bi bi-box-seam"></i> PAK SUPPLY</span><span><i class="bi bi-compass"></i>
                    HORIZON</span></div>
        </div>
    </section>

    <section class="pp-section pp-overview" id="platform">
        <div class="container">
            <div class="pp-section-heading text-center" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">THE PROFIT
                    POINT PLATFORM</span>
                <h2>Run your whole business<br>from one <span>calm command center.</span></h2>
                <p>Replace disconnected spreadsheets and guesswork with a single source of truth for every team,
                    transaction, and decision.</p>
            </div>
            <div class="row g-4 align-items-stretch pp-overview-grid">
                <div class="col-lg-7" data-reveal>
                    <article class="pp-overview-card pp-overview-main">
                        <div><span class="pp-icon-gradient"><i class="bi bi-grid-1x2-fill"></i></span>
                            <h3>A complete operating system</h3>
                            <p>Every number, task, and team member in sync—from the warehouse floor to your next big
                                decision.</p><a href="#features" data-tf-smooth>Explore capabilities <i
                                    class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="pp-overview-tiles"><span><i class="bi bi-boxes"></i> Inventory</span><span><i
                                    class="bi bi-receipt"></i> Invoices</span><span><i class="bi bi-truck"></i>
                                Delivery</span><span><i class="bi bi-journal-text"></i> Khata</span></div>
                    </article>
                </div>
                <div class="col-lg-5 d-grid gap-4" data-reveal data-reveal-delay="100">
                    <article class="pp-overview-card pp-overview-small"><span class="pp-icon-gradient is-green"><i
                                class="bi bi-lightning-charge-fill"></i></span>
                        <h3>Made for momentum</h3>
                        <p>Intuitive enough for every team, powerful enough for every growing operation.</p>
                        <div class="pp-pulse-line"><span></span><span></span><span></span><span></span><strong>Live
                                operations</strong></div>
                    </article>
                    <article class="pp-overview-card pp-overview-small pp-overview-trust"><span
                            class="pp-icon-gradient is-violet"><i class="bi bi-shield-lock-fill"></i></span>
                        <h3>Control without compromise</h3>
                        <p>Role-based access, business verification, and complete visibility for owners.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="pp-section pp-dashboard-section" id="dashboard">
        <div class="container">
            <div class="row align-items-end g-4 mb-5">
                <div class="col-lg-7 pp-section-heading" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">ONE SOURCE OF
                        TRUTH</span>
                    <h2>See the story behind<br>your business.</h2>
                </div>
                <div class="col-lg-5" data-reveal>
                    <p class="pp-section-intro">A live command center turns daily activity into decisions you can make with
                        confidence.</p>
                </div>
            </div>
            <div class="pp-dashboard-showcase" data-reveal>
                <div class="pp-ds-sidebar">
                    <div class="pp-ds-brand"><i class="bi bi-boxes"></i><span>Profit Point</span></div>
                    <small>WORKSPACE</small><a class="active"><i class="bi bi-grid-1x2"></i> Overview</a><a><i
                            class="bi bi-bag-check"></i> Orders <b>8</b></a><a><i class="bi bi-box"></i> Inventory</a><a><i
                            class="bi bi-people"></i> Customers</a><small>MANAGE</small><a><i class="bi bi-truck"></i>
                        Deliveries</a><a><i class="bi bi-journal-text"></i> Khata</a><a><i class="bi bi-bar-chart"></i>
                        Reports</a>
                    <div class="pp-ds-person"><span>AK</span>
                        <div><strong>Ahmad Khan</strong><small>Administrator</small></div><i class="bi bi-three-dots"></i>
                    </div>
                </div>
                <div class="pp-ds-content">
                    <header>
                        <div><span>Overview</span>
                            <h3>Welcome back, Ahmad <i>✦</i></h3>
                        </div>
                        <div><button class="pp-ds-search"><i class="bi bi-search"></i> Search anything</button><button
                                class="pp-ds-bell"><i class="bi bi-bell"></i><b></b></button><button class="pp-ds-add"><i
                                    class="bi bi-plus-lg"></i> Create</button></div>
                    </header>
                    <div class="pp-ds-date"><span><i class="bi bi-calendar3"></i> Aug 1 – Aug 7, 2026</span><button>Export
                            <i class="bi bi-download"></i></button></div>
                    <div class="pp-metric-grid">
                        <article><span class="pp-metric-icon blue"><i class="bi bi-currency-rupee"></i></span><small>Total
                                revenue</small><strong>Rs 1,284,500</strong><em class="up"><i class="bi bi-arrow-up"></i>
                                18.4% <b>vs last week</b></em></article>
                        <article><span class="pp-metric-icon violet"><i class="bi bi-bag-check"></i></span><small>Total
                                orders</small><strong>1,248</strong><em class="up"><i class="bi bi-arrow-up"></i> 12.8%
                                <b>vs last week</b></em></article>
                        <article><span class="pp-metric-icon amber"><i class="bi bi-box-seam"></i></span><small>Low
                                stock</small><strong>12 <b>items</b></strong><em class="neutral"><i
                                    class="bi bi-exclamation-circle"></i> Needs attention</em></article>
                        <article><span class="pp-metric-icon green"><i class="bi bi-wallet2"></i></span><small>Khata
                                balance</small><strong>Rs 91,240</strong><em class="down"><i class="bi bi-arrow-down"></i>
                                4.2% <b>vs last week</b></em></article>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <article class="pp-panel pp-sales-panel">
                                <div class="pp-panel-head">
                                    <div>
                                        <h4>Revenue overview</h4><span>Performance across all sales channels</span>
                                    </div><button>Revenue <i class="bi bi-chevron-down"></i></button>
                                </div>
                                <div class="pp-line-chart">
                                    <div class="pp-chart-y">
                                        <span>200k</span><span>150k</span><span>100k</span><span>50k</span><span>0</span>
                                    </div><svg viewBox="0 0 650 210" role="img"
                                        aria-label="Revenue chart increasing through the week" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="chartfill" x1="0" x2="0" y1="0" y2="1">
                                                <stop stop-color="#2563eb" stop-opacity=".22" />
                                                <stop offset="1" stop-color="#2563eb" stop-opacity="0" />
                                            </linearGradient>
                                        </defs>
                                        <path
                                            d="M0,170 C42,155 58,140 95,151 S150,158 185,122 S240,132 275,108 S330,132 370,76 S425,95 462,62 S515,70 552,35 S615,52 650,17 L650,210 L0,210Z"
                                            fill="url(#chartfill)" />
                                        <path
                                            d="M0,170 C42,155 58,140 95,151 S150,158 185,122 S240,132 275,108 S330,132 370,76 S425,95 462,62 S515,70 552,35 S615,52 650,17"
                                            fill="none" stroke="#2563eb" stroke-width="3" />
                                    </svg>
                                    <div class="pp-chart-x"><span>Aug 1</span><span>Aug 2</span><span>Aug 3</span><span>Aug
                                            4</span><span>Aug 5</span><span>Aug 6</span><span>Aug 7</span></div>
                                </div>
                            </article>
                        </div>
                        <div class="col-lg-4">
                            <article class="pp-panel pp-delivery-panel">
                                <div class="pp-panel-head">
                                    <div>
                                        <h4>Delivery status</h4><span>This week</span>
                                    </div><i class="bi bi-three-dots"></i>
                                </div>
                                <div class="pp-donut">
                                    <div><strong>86%</strong><span>on time</span></div>
                                </div>
                                <div class="pp-delivery-key"><span><i class="green"></i> Delivered <b>186</b></span><span><i
                                            class="blue"></i> In transit <b>24</b></span></div>
                            </article>
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-lg-7">
                            <article class="pp-panel pp-activity">
                                <div class="pp-panel-head">
                                    <div>
                                        <h4>Recent activity</h4><span>Your latest business updates</span>
                                    </div><a>View all</a>
                                </div>
                                <div class="pp-activity-item"><span class="blue"><i class="bi bi-bag-check"></i></span>
                                    <div><strong>New order from Al-Madina Store</strong><small>Order #ORD-1294 · 2 minutes
                                            ago</small></div><b>Rs 28,400</b>
                                </div>
                                <div class="pp-activity-item"><span class="green"><i class="bi bi-wallet2"></i></span>
                                    <div><strong>Payment received from Ahmed Traders</strong><small>Invoice #INV-882 · 18
                                            minutes ago</small></div><b>Rs 46,200</b>
                                </div>
                            </article>
                        </div>
                        <div class="col-lg-5">
                            <article class="pp-panel pp-notifications">
                                <div class="pp-panel-head">
                                    <div>
                                        <h4>Needs your attention</h4><span>2 new notifications</span>
                                    </div>
                                </div>
                                <div><i class="bi bi-exclamation-triangle"></i>
                                    <p><strong>12 products are running low</strong><br><span>Review stock levels before the
                                            next order cycle.</span></p><a>Review inventory <i
                                            class="bi bi-arrow-right"></i></a>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pp-section pp-roles" id="roles">
        <div class="container">
            <div class="pp-section-heading text-center" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">BUILT AROUND
                    YOUR TEAM</span>
                <h2>Every role, perfectly in sync.</h2>
                <p>Give every person a focused workspace, while keeping the whole business connected.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-reveal>
                    <article class="pp-role-card"><span class="pp-role-avatar is-owner">SK</span>
                        <p>For leaders</p>
                        <h3>Business Owner</h3><span>See the whole picture and act on what matters.</span>
                        <ul>
                            <li><i class="bi bi-check2"></i> Live business health</li>
                            <li><i class="bi bi-check2"></i> Team & permissions</li>
                        </ul>
                    </article>
                </div>
                <div class="col-md-6 col-lg-3" data-reveal data-reveal-delay="80">
                    <article class="pp-role-card"><span class="pp-role-avatar is-sales">AF</span>
                        <p>For growth teams</p>
                        <h3>Sales & Orders</h3><span>Convert every conversation into a seamless sale.</span>
                        <ul>
                            <li><i class="bi bi-check2"></i> Fast order entry</li>
                            <li><i class="bi bi-check2"></i> Customer history</li>
                        </ul>
                    </article>
                </div>
                <div class="col-md-6 col-lg-3" data-reveal data-reveal-delay="140">
                    <article class="pp-role-card"><span class="pp-role-avatar is-stock">IR</span>
                        <p>For operations</p>
                        <h3>Inventory Manager</h3><span>Know exactly what’s moving and what needs attention.</span>
                        <ul>
                            <li><i class="bi bi-check2"></i> Stock intelligence</li>
                            <li><i class="bi bi-check2"></i> Purchase receiving</li>
                        </ul>
                    </article>
                </div>
                <div class="col-md-6 col-lg-3" data-reveal data-reveal-delay="200">
                    <article class="pp-role-card"><span class="pp-role-avatar is-delivery">ZH</span>
                        <p>For the field</p>
                        <h3>Delivery Team</h3><span>Move deliveries forward with less back-and-forth.</span>
                        <ul>
                            <li><i class="bi bi-check2"></i> Daily delivery list</li>
                            <li><i class="bi bi-check2"></i> Collection updates</li>
                        </ul>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="pp-section pp-features" id="features">
        <div class="container">
            <div class="row justify-content-between align-items-end mb-5">
                <div class="col-lg-7 pp-section-heading" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">DESIGNED FOR
                        REAL OPERATIONS</span>
                    <h2>Everything works<br>better together.</h2>
                </div>
                <div class="col-lg-4" data-reveal>
                    <p class="pp-section-intro">From the warehouse shelf to the final payment, Profit Point keeps your
                        everyday work moving in one direction.</p>
                </div>
            </div>
            <div class="row g-4">
                @foreach([['bi-boxes', 'Inventory Management', 'Know what you have, what is moving, and what needs reordering.', 'blue'], ['bi-bag-check', 'Order Management', 'Create, track, and fulfill orders without the busywork.', 'violet'], ['bi-journal-text', 'Khata Ledger', 'Keep every customer balance accurate, visible, and easy to settle.', 'amber'], ['bi-wallet2', 'Payment Tracking', 'Record cash, bank, and digital payments in one clean timeline.', 'green'], ['bi-truck', 'Delivery Management', 'Assign, follow up, and complete deliveries with confidence.', 'blue'], ['bi-bar-chart-line', 'Reporting', 'Turn daily transactions into a clear picture of performance.', 'violet'], ['bi-people', 'Staff Management', 'Give the right people the right access, nothing more.', 'amber'], ['bi-graph-up-arrow', 'Business Analytics', 'Spot trends early and make decisions backed by live data.', 'green'], ['bi-person-vcard', 'Customer Management', 'Build stronger customer relationships with complete history.', 'blue'], ['bi-bell', 'Smart Notifications', 'Stay ahead of low stock, collections, and urgent tasks.', 'violet']] as [$icon, $title, $copy, $tone])
                    <div class="col-md-6 col-lg-4 col-xl-3" data-reveal>
                        <article class="pp-feature-card"><span class="pp-feature-icon {{ $tone }}"><i
                                    class="bi {{ $icon }}"></i></span>
                            <h3>{{ $title }}</h3>
                            <p>{{ $copy }}</p><a href="{{ route('public.features') }}"
                                aria-label="Learn more about {{ $title }}"><i class="bi bi-arrow-up-right"></i></a>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pp-section pp-why">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-reveal>
                    <div class="pp-why-visual">
                        <div class="pp-why-card pp-why-main"><span>Business health</span><strong>Thriving <i
                                    class="bi bi-arrow-up-right"></i></strong>
                            <div class="pp-health-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                            <small>Everything is on track this month.</small>
                        </div>
                        <div class="pp-why-card pp-why-check"><span><i class="bi bi-check-lg"></i></span>
                            <div><strong>Payment collected</strong><small>Rs 46,200 from Ahmed Traders</small></div>
                        </div>
                        <div class="pp-why-card pp-why-team">
                            <div class="pp-avatars"><span>AS</span><span>FA</span><span>MK</span></div><strong>Built for
                                your whole team</strong>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 pp-why-content" data-reveal data-reveal-delay="100"><span
                        class="pp-eyebrow pp-eyebrow-blue">WHY PROFIT POINT</span>
                    <h2>Less chasing.<br>More <span>progress.</span></h2>
                    <p>Most business software adds another layer of complexity. Profit Point removes it—so your team can
                        focus on the work that actually grows your business.</p>
                    <div class="pp-benefit"><span><i class="bi bi-check2"></i></span>
                        <div>
                            <h3>Built around your workflow</h3>
                            <p>Familiar language and practical tools shaped for wholesale businesses.</p>
                        </div>
                    </div>
                    <div class="pp-benefit"><span><i class="bi bi-check2"></i></span>
                        <div>
                            <h3>Clarity at every level</h3>
                            <p>From a single order to a monthly report, understand what is happening instantly.</p>
                        </div>
                    </div>
                    <div class="pp-benefit"><span><i class="bi bi-check2"></i></span>
                        <div>
                            <h3>Ready to grow with you</h3>
                            <p>Start focused today and add more control as your operation expands.</p>
                        </div>
                    </div>
                </div>
            </div>
    </section>

    <section class="pp-section pp-workflow" id="how-it-works">
        <div class="container">
            <div class="pp-section-heading text-center" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">A SIMPLER WAY
                    FORWARD</span>
                <h2>From first login to<br>full control, fast.</h2>
            </div>
            <div class="pp-timeline">
                <div class="pp-timeline-line"></div>
                @foreach([['01', 'Create your workspace', 'Register your business and get your tailored account ready in minutes.', 'bi-building-add'], ['02', 'Bring in your essentials', 'Add products, opening stock, customers, and your team at your own pace.', 'bi-box-arrow-in-down'], ['03', 'Run your day with clarity', 'Manage orders, payments, delivery, and khata from one live workspace.', 'bi-play-circle'], ['04', 'Grow with confidence', 'Use real-time insight to make the next best move for your business.', 'bi-graph-up-arrow']] as [$number, $title, $copy, $icon])
                    <article data-reveal><span class="pp-timeline-number">{{ $number }}</span><span class="pp-timeline-icon"><i
                                class="bi {{ $icon }}"></i></span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $copy }}</p>
                </article>@endforeach
            </div>
        </div>
    </section>

    <section class="pp-section pp-testimonials">
        <div class="container">
            <div class="row align-items-end g-4 mb-5">
                <div class="col-lg-7 pp-section-heading" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">LOVED BY TEAMS
                        IN TRADE</span>
                    <h2>The kind of clarity<br>you can feel.</h2>
                </div>
                <div class="col-lg-5" data-reveal>
                    <p class="pp-section-intro">Business owners and their teams use Profit Point to create calmer, more
                        capable operations.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-5" data-reveal>
                    <article class="pp-testimonial-card pp-testimonial-featured">
                        <div class="pp-stars">★★★★★</div>
                        <blockquote>“Before Profit Point, closing the day meant chasing numbers across notebooks and
                            messages. Now, I open one dashboard and know exactly where we stand.”</blockquote>
                        <div class="pp-testimonial-person"><span class="pp-person-avatar blue">SA</span>
                            <div><strong>Sarah Ahmed</strong><small>Owner, Ahmed & Sons Wholesale</small></div>
                        </div>
                    </article>
                </div>
                <div class="col-lg-7 d-grid gap-4">
                    <article class="pp-testimonial-card pp-testimonial-compact" data-reveal data-reveal-delay="80">
                        <div class="pp-stars">★★★★★</div>
                        <blockquote>“Our delivery team finally has a shared view of what needs to go out. It has made a real
                            difference to our customer experience.”</blockquote>
                        <div class="pp-testimonial-person"><span class="pp-person-avatar violet">MK</span>
                            <div><strong>Mohammad Kashif</strong><small>Operations Manager, Apex Distribution</small></div>
                        </div>
                    </article>
                    <article class="pp-testimonial-card pp-testimonial-compact" data-reveal data-reveal-delay="140">
                        <div class="pp-stars">★★★★★</div>
                        <blockquote>“The Khata view alone has saved us hours every week. Every balance is clear, every
                            payment is accounted for.”</blockquote>
                        <div class="pp-testimonial-person"><span class="pp-person-avatar green">RH</span>
                            <div><strong>Rida Hassan</strong><small>Founder, Horizon Retail Supply</small></div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="pp-section pp-pricing" id="pricing">
        <div class="container">
            <div class="pp-section-heading text-center" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">START WITH
                    CONFIDENCE</span>
                <h2>Try the complete<br>workspace for free.</h2>
                <p>Start immediately with a full trial. When you are ready to continue, our team will create a subscription
                    tailored to your business.</p>
                <div class="d-flex justify-content-center flex-wrap gap-3 mt-4"><a href="{{ route('register.business') }}"
                        class="btn pp-btn-primary">Start Free Trial <i class="bi bi-arrow-up-right"></i></a><a
                        href="{{ route('public.contact') }}" class="btn pp-btn-secondary">Contact Sales</a></div>
            </div>
            <p class="pp-pricing-note" data-reveal><i class="bi bi-shield-check"></i> No credit card required &nbsp;·&nbsp;
                Full workspace access &nbsp;·&nbsp; Personal onboarding support</p>
        </div>
    </section>

    <section class="pp-section pp-faq" id="faq">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5" data-reveal><span class="pp-eyebrow pp-eyebrow-blue">QUESTIONS, ANSWERED</span>
                    <h2>Everything you need to know.</h2>
                    <p>Still curious? Our team is always ready to help you find the right way forward.</p><a
                        href="{{ route('public.contact') }}" class="pp-text-link">Talk to our team <i
                            class="bi bi-arrow-right"></i></a>
                </div>
                <div class="col-lg-7" data-reveal data-reveal-delay="80">
                    <div class="accordion pp-faq-accordion" id="faqAccordion">
                        @foreach(['Can I try Profit Point before choosing a plan?' => 'Yes. Eligible plans include a free trial, so you can set up your workspace and see how it fits your daily operation before committing.', 'Is Profit Point suitable for my type of wholesale business?' => 'Profit Point is designed for manufacturers, distributors, wholesalers, retailers, and the teams that support them. Each business can configure its own products, customers, staff, and workflow.', 'Can I give my staff limited access?' => 'Absolutely. Business owners can create roles and choose the exact permissions each team member needs, so everyone stays focused and sensitive information stays protected.', 'How are payments recorded?' => 'You can record cash, bank transfer, JazzCash, Easypaisa, cheque, and other payment methods with dates, references, and proof—without needing paid payment APIs.', 'Can I track customer credit and Khata?' => 'Yes. Each customer has a clear running ledger, so you can see credit, debit, payments, and outstanding balance whenever you need it.', 'Will my business data be secure?' => 'Yes. Profit Point uses role-based access and separate business workspaces, while platform verification and audit-friendly records help keep operations accountable.'] as $question => $answer)
                            <div class="accordion-item">
                                <h3 class="accordion-header"><button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#ppFaq{{ $loop->index }}">{{ $question }}</button></h3>
                                <div id="ppFaq{{ $loop->index }}" class="accordion-collapse collapse"
                                    data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">{{ $answer }}</div>
                                </div>
                        </div>@endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pp-final-cta" id="contact">
        <div class="container">
            <div class="pp-final-card" data-reveal>
                <div class="pp-final-orb"></div><span class="pp-eyebrow">YOUR NEXT CLEARER DAY STARTS HERE</span>
                <h2>Run your wholesale<br>business with <span>confidence.</span></h2>
                <p>Join the businesses replacing daily chaos with a smarter, simpler way to grow.</p>
                <div class="d-flex justify-content-center flex-wrap gap-3"><a href="{{ route('register.business') }}"
                        class="btn pp-btn-white btn-lg">Start free trial <i class="bi bi-arrow-up-right"></i></a><a
                        href="{{ route('public.contact') }}" class="btn pp-btn-ghost btn-lg">Talk to sales</a></div><small
                    class="pp-final-trust"><span><i class="bi bi-check-circle-fill"></i> No credit card
                        required</span><span><i class="bi bi-check-circle-fill"></i> Set up in minutes</span></small>
            </div>
        </div>
    </section>

    @if($hasPublicDemo)
        @php($initialDemo = $publicDemos->first())
        <div class="modal fade pp-demo-modal" id="profitPointDemoModal" tabindex="-1" aria-labelledby="profitPointDemoTitle"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="pp-demo-modal__copy">
                            <h2 class="modal-title h4 mb-1" id="profitPointDemoTitle">{{ $initialDemo['title'] }}</h2>
                            <p class="tf-muted mb-0 small {{ empty($initialDemo['subtitle']) ? 'd-none' : '' }}" id="profitPointDemoSubtitle">{{ $initialDemo['subtitle'] }}</p>
                        </div>
                        <div class="pp-demo-modal__actions">
                            @if($publicDemos->count() > 1)
                                <div class="pp-demo-language-switch" role="tablist" aria-label="Demo language">
                                    @foreach($publicDemos as $locale => $demo)
                                        <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-demo-language="{{ $locale }}" data-demo-title="{{ $demo['title'] }}" data-demo-subtitle="{{ $demo['subtitle'] }}" data-demo-url="{{ $demo['url'] }}" data-demo-poster="{{ $demo['poster'] }}" @if($locale === 'ur') lang="ur" dir="rtl" @endif>{{ $demo['label'] }}</button>
                                    @endforeach
                                </div>
                            @endif
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close demo"></button>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="pp-demo-player"><video controls autoplay muted preload="metadata" playsinline data-demo-player data-demo-url="{{ $initialDemo['url'] }}" data-demo-poster="{{ $initialDemo['poster'] }}">Your browser does not support video playback.</video></div>
                    </div>
                    <div class="modal-footer pp-demo-modal__footer">
                        <a href="{{ route('register.business') }}" class="btn pp-btn-primary btn-sm">Start free trial <i class="bi bi-arrow-up-right"></i></a>
                        <a href="{{ route('public.contact') }}" class="btn pp-btn-secondary btn-sm">Contact us</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($hasPublicWhatsApp)
        <a class="pp-whatsapp-float" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer"
            aria-label="{{ $platformSettings->whatsapp_tooltip ?: 'Chat with us on WhatsApp' }}"
            data-tooltip="{{ $platformSettings->whatsapp_tooltip ?: 'Chat with us on WhatsApp' }}"><i class="bi bi-whatsapp"
                aria-hidden="true"></i><span
                class="visually-hidden">{{ $platformSettings->whatsapp_tooltip ?: 'Chat with us on WhatsApp' }}</span></a>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const reveal = document.querySelectorAll('[data-reveal]');
            const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), { threshold: .12 });
            reveal.forEach(item => { item.style.setProperty('--reveal-delay', `${item.dataset.revealDelay || 0}ms`); observer.observe(item); });
            document.querySelectorAll('[data-parallax-root]').forEach(root => root.addEventListener('pointermove', event => { const x = (event.clientX / innerWidth - .5) * 2, y = (event.clientY / innerHeight - .5) * 2; root.querySelectorAll('[data-parallax]').forEach(item => { const amount = Number(item.dataset.parallax); item.style.transform = `translate(${x * amount * 40}px, ${y * amount * 28}px)`; }); }));
            const nav = document.querySelector('.pp-nav');
            const syncNav = () => nav?.classList.toggle('is-scrolled', scrollY > 12);
            syncNav(); window.addEventListener('scroll', syncNav, { passive: true });
            const navLinks = [...document.querySelectorAll('.pp-nav-links a[href*="#"]')];
            const navSections = navLinks.map(link => [link, document.querySelector(new URL(link.href).hash)]).filter(([, section]) => section);
            const navObserver = new IntersectionObserver(entries => entries.forEach(entry => { if (!entry.isIntersecting) return; navLinks.forEach(link => link.classList.toggle('is-active', link.getAttribute('href').endsWith(`#${entry.target.id}`))); }), { rootMargin: '-35% 0px -58% 0px', threshold: 0 });
            navSections.forEach(([, section]) => navObserver.observe(section));
            document.querySelectorAll('.pp-btn-primary, .pp-btn-secondary, .pp-btn-white, .pp-btn-ghost, .pp-nav-cta').forEach(button => button.addEventListener('click', event => { const ripple = document.createElement('span'), rect = button.getBoundingClientRect(); ripple.className = 'pp-ripple'; ripple.style.left = `${event.clientX - rect.left}px`; ripple.style.top = `${event.clientY - rect.top}px`; button.append(ripple); ripple.addEventListener('animationend', () => ripple.remove()); }));
            const demoModal = document.getElementById('profitPointDemoModal');
            const demoPlayer = demoModal?.querySelector('[data-demo-player]');
            const stopDemo = () => {
                if (!demoPlayer) return;
                demoPlayer.pause();
                try { demoPlayer.currentTime = 0; } catch (_) {}
                demoPlayer.removeAttribute('src');
                demoPlayer.load();
            };
            const loadDemo = button => {
                if (!demoPlayer || !button) return;
                stopDemo();
                demoPlayer.muted = true;
                demoPlayer.defaultMuted = true;
                demoPlayer.poster = button.dataset.demoPoster || '';
                demoPlayer.src = button.dataset.demoUrl || demoPlayer.dataset.demoUrl || '';
                demoPlayer.load();
                const autoplay = () => demoPlayer.play().catch(() => {});
                demoPlayer.addEventListener('canplay', autoplay, { once: true });
                const title = demoModal.querySelector('#profitPointDemoTitle'), subtitle = demoModal.querySelector('#profitPointDemoSubtitle');
                if (title) title.textContent = button.dataset.demoTitle || title.textContent;
                if (subtitle) { subtitle.textContent = button.dataset.demoSubtitle || ''; subtitle.classList.toggle('d-none', !button.dataset.demoSubtitle); }
            };
            demoModal?.addEventListener('show.bs.modal', () => loadDemo(demoModal.querySelector('[data-demo-language].is-active') || demoPlayer));
            demoModal?.querySelectorAll('[data-demo-language]').forEach(button => button.addEventListener('click', () => { demoModal.querySelectorAll('[data-demo-language]').forEach(item => { const selected = item === button; item.classList.toggle('is-active', selected); item.setAttribute('aria-selected', selected ? 'true' : 'false'); }); loadDemo(button); }));
            demoModal?.addEventListener('hidden.bs.modal', stopDemo);
        });
    </script>
@endpush
