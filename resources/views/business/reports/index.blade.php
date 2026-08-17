@extends('layouts.dashboard')

@section('page-title', 'Business Reports')
@section('page-subtitle', 'Sales, inventory, profitability, and payable analytics')

@section('content')
@php
    $money = static function ($value): string {
        $amount = (float) $value;
        return 'Rs '.number_format($amount, floor($amount) === $amount ? 0 : 2);
    };
    $quantity = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $chartRows = collect($chartSeries ?? [])->take(-12);
    $salesChartMax = max(1, (float) $chartRows->max('net_sales'));
    $comparisonChartMax = max(1, (float) $chartRows->flatMap(fn ($row) => [$row['net_sales'], $row['expenses']])->max());
    $profitChartMax = max(1, (float) $chartRows->flatMap(fn ($row) => [abs($row['gross_profit']), abs($row['net_profit'])])->max());
    $permissionService = app(\App\Services\CompanyPermissionService::class);
    $canViewFinanceReports = $permissionService->allowsUser(auth()->user(), 'reports.finance_reports');
    $canViewSalesAnalytics = $permissionService->allowsUser(auth()->user(), 'reports.sales_analytics');
    $canViewInventoryAnalytics = $permissionService->allowsUser(auth()->user(), 'reports.inventory_analytics');
    $primaryMetrics = [
        ['Net Sales', $money($netSales), 'After discounts and returns', 'bi-receipt', 'blue'],
        ['Gross Profit', $money($grossProfit), 'Net sales less COGS', 'bi-graph-up-arrow', $grossProfit < 0 ? 'red' : 'green'],
        ['Outstanding Receivables', $money($outstandingReceivables), 'Open sales balance', 'bi-wallet2', 'amber'],
        ['Supplier Payables', $money($totalPayables), 'Open purchases in period', 'bi-building-exclamation', 'orange'],
        [$netProfit < 0 ? 'Net Loss' : 'Net Profit', $money($netProfit), 'After operating expenses', 'bi-pie-chart', $netProfit < 0 ? 'red' : 'green'],
    ];
    $metricGroups = [
        ['Sales Performance', 'Live sales activity for the selected period', [
            ['Gross Sales', $money($grossSales), 'Before discounts and returns', 'bi-cash-stack', 'blue'],
            ['Discounts', $money($salesDiscounts), 'Applied to selected sales', 'bi-percent', 'slate'],
            ['Revenue Received', $money($revenueReceived), 'Payments received in period', 'bi-check2-circle', 'green'],
            ['Completed Orders', number_format($completedOrders), 'Selected period', 'bi-bag-check', 'blue'],
            ['Pending Orders', number_format($pendingOrders), 'Awaiting completion', 'bi-hourglass-split', 'amber'],
        ]],
        ['Inventory Position', 'Current stock position', [
            ['Stock Value', $money($stockValue), 'Current inventory', 'bi-boxes', 'blue'],
            ['Low Stock', number_format($lowStockCount), 'Products needing review', 'bi-exclamation-triangle', 'amber'],
            ['Out of Stock', number_format($outOfStockCount), 'Quantity is zero', 'bi-x-octagon', 'red'],
        ]],
        ['Profitability', 'Margins and operating result', [
            ['COGS', $money($cogs), 'Cost of goods sold', 'bi-box-arrow-down', 'slate'],
            ['Gross Profit', $money($grossProfit), 'Net sales less COGS', 'bi-graph-up', $grossProfit < 0 ? 'red' : 'green'],
            ['Expenses', $money($expenses), 'Operating expenses', 'bi-receipt-cutoff', 'orange'],
            [$netProfit < 0 ? 'Net Loss' : 'Net Profit', $money($netProfit), 'Gross profit less expenses', 'bi-pie-chart', $netProfit < 0 ? 'red' : 'green'],
        ]],
        ['Supplier Exposure', 'Open supplier commitments', [
            ['Supplier Payables', $money($totalPayables), 'Open purchases in period', 'bi-building-exclamation', 'orange'],
            ['Due Today', $money($dueTodayPayables), 'Payment due today', 'bi-calendar-event', 'amber'],
            ['Due Soon', $money($dueSoonPayables), 'Due within seven days', 'bi-calendar-week', 'amber'],
            ['Overdue', $money($overduePayables), 'Past due date', 'bi-exclamation-circle', 'red'],
        ]],
    ];
    if (! $canViewFinanceReports) {
        $primaryMetrics = array_values(array_filter($primaryMetrics, fn (array $metric) => ! in_array($metric[0], ['Gross Profit', 'Net Profit', 'Net Loss'], true)));
        $metricGroups = array_values(array_filter($metricGroups, fn (array $group) => ! in_array($group[0], ['Profitability', 'Supplier Exposure'], true)));
    }
@endphp

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    @companyCan('reports.finance_reports')
        @companyCan('accounting.view')
            @companyCan('sales.view')
                @companyCan('expenses.view')
                    <a href="{{ route('business.reports.end-of-day') }}" class="btn btn-sm btn-tf-primary"><i class="bi bi-calendar2-check me-1"></i>End of Day</a>
                @endcompanyCan
            @endcompanyCan
        @endcompanyCan
    @endcompanyCan
    @companyCan('customers.view')<a href="{{ route('business.reports.customer-aging') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-people me-1"></i>Customer Aging</a>@endcompanyCan
    @companyCan('suppliers.view')<a href="{{ route('business.reports.supplier-aging') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-building me-1"></i>Supplier Aging</a>@endcompanyCan
    @companyCan('inventory.view')
        @companyCan('reports.inventory_analytics')
            <a href="{{ route('business.reports.stock-movement-analytics') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up-arrow me-1"></i>Stock Movement Analytics</a>
        @endcompanyCan
    @endcompanyCan
    @companyCan('sales.view')
        @companyCan('reports.sales_analytics')
            @companyCan('reports.finance_reports')
                <a href="{{ route('business.reports.product-performance') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart-line me-1"></i>Product Performance</a>
            @endcompanyCan
        @endcompanyCan
    @endcompanyCan
</div>

<section class="tf-report-filter-bar" aria-label="Report filters">
    <form method="GET" id="reportFilters" class="tf-report-filter-form">
        <div class="tf-report-filter-fields">
            <div class="tf-report-filter-field">
                <label for="reportPeriod">Period</label>
                <select name="period" id="reportPeriod" class="form-select" data-report-period>
                    <option value="today" @selected($filters['period'] === 'today')>Today</option>
                    <option value="this_week" @selected($filters['period'] === 'this_week')>This Week</option>
                    <option value="this_month" @selected($filters['period'] === 'this_month')>This Month</option>
                    <option value="this_year" @selected($filters['period'] === 'this_year')>This Year</option>
                    <option value="custom" @selected($filters['period'] === 'custom')>Custom Range</option>
                </select>
            </div>
            <div class="tf-report-filter-field report-custom-range {{ $filters['period'] === 'custom' ? '' : 'd-none' }}">
                <label for="reportDateFrom">Date From</label>
                <input name="date_from" id="reportDateFrom" type="date" value="{{ $filters['from']->toDateString() }}" class="form-control" {{ $filters['period'] === 'custom' ? '' : 'disabled' }}>
            </div>
            <div class="tf-report-filter-field report-custom-range {{ $filters['period'] === 'custom' ? '' : 'd-none' }}">
                <label for="reportDateTo">Date To</label>
                <input name="date_to" id="reportDateTo" type="date" value="{{ $filters['to']->toDateString() }}" class="form-control" {{ $filters['period'] === 'custom' ? '' : 'disabled' }}>
            </div>
            <div class="tf-report-filter-field">
                <label for="reportStatus">Status</label>
                <select name="status" id="reportStatus" class="form-select"><option value="">All Statuses</option>@foreach(['New', 'Pending', 'Accepted', 'Packing', 'Ready', 'Delivered', 'Completed', 'Partially Returned', 'Returned', 'Cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach</select>
            </div>
            <div class="tf-report-filter-field">
                <label for="reportCustomer">Customer</label>
                <select name="customer_id" id="reportCustomer" class="form-select"><option value="">All Customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->business_name ?: $customer->name }}</option>@endforeach</select>
            </div>
            <div class="tf-report-filter-field">
                <label for="reportProduct">Product</label>
                <select name="product_id" id="reportProduct" class="form-select"><option value="">All Products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select>
            </div>
        </div>
        <div class="tf-report-filter-actions"><button class="btn btn-tf-primary" type="submit"><i class="bi bi-funnel"></i>Apply Filters</button><a href="{{ route('business.reports') }}" class="btn btn-outline-primary">Clear</a></div>
    </form>
    <div class="tf-report-period"><i class="bi bi-calendar3"></i><span>Reporting Period: <strong>{{ $filters['from']->format('n/j/Y') }} &ndash; {{ $filters['to']->format('n/j/Y') }}</strong></span></div>
</section>

<section class="tf-report-summary" aria-labelledby="reportSummaryHeading">
    <div class="tf-report-section-title"><div><span>Executive summary</span><h2 id="reportSummaryHeading">Business performance</h2></div><p>Key results for the selected reporting period.</p></div>
    <div class="tf-report-primary-grid">
        @foreach($primaryMetrics as [$label, $value, $meta, $icon, $tone])
            <article class="tf-report-primary-card is-{{ $tone }}"><div class="tf-report-metric-icon"><i class="bi {{ $icon }}"></i></div><div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $meta }}</small></div></article>
        @endforeach
    </div>
</section>

<section class="tf-report-metric-groups" aria-label="Report metric groups">
    @foreach($metricGroups as [$title, $description, $metrics])
        <article class="tf-report-metric-group">
            <header><div><h2>{{ $title }}</h2><p>{{ $description }}</p></div>@if($title === 'Profitability')<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#profitabilityBreakdownModal"><i class="bi bi-list-columns-reverse me-1"></i>View Breakdown</button>@endif</header>
            @if($title === 'Profitability')
                <div class="tf-profitability-waterfall" aria-label="Profitability calculation">
                    <div class="tf-profitability-waterfall__row"><span>Gross Sales</span><strong>{{ $money($profitability['gross_sales']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-deduction"><span>Sales Returns</span><strong>-{{ $money($profitability['sales_returns']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-deduction"><span>Invoice Discounts</span><strong>-{{ $money($profitability['invoice_discounts']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-total"><span>Net Sales</span><strong>{{ $money($profitability['net_sales']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-deduction"><span>Cost of Goods Sold</span><strong>-{{ $money($profitability['cogs']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-total"><span>Gross Profit</span><strong class="{{ $profitability['gross_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($profitability['gross_profit']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-deduction"><span>Operating Expenses</span><strong>-{{ $money($profitability['expenses']) }}</strong></div>
                    <div class="tf-profitability-waterfall__row is-result {{ $profitability['net_profit'] < 0 ? 'is-loss' : '' }}"><span>{{ $profitability['net_profit'] < 0 ? 'Net Loss' : 'Net Profit' }}</span><strong>{{ $money($profitability['net_profit']) }}</strong></div>
                </div>
                @if($profitability['line_discounts_included'] > 0)
                    <p class="tf-profitability-waterfall__note mb-0">Includes {{ $money($profitability['line_discounts_included']) }} in line discounts already reflected in saved sales totals.</p>
                @endif
            @else
                <div class="tf-report-mini-grid tf-report-mini-grid--{{ count($metrics) }}">
                    @foreach($metrics as [$label, $value, $meta, $icon, $tone])
                        <div class="tf-report-mini-card"><span class="tf-report-mini-card__icon is-{{ $tone }}"><i class="bi {{ $icon }}"></i></span><div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $meta }}</small></div></div>
                    @endforeach
                </div>
            @endif
        </article>
    @endforeach
</section>

@if($canViewFinanceReports)
<div class="modal fade" id="profitabilityBreakdownModal" tabindex="-1" aria-labelledby="profitabilityBreakdownTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title h5 mb-1" id="profitabilityBreakdownTitle">Profitability breakdown</h2><p class="tf-muted small mb-0">{{ $filters['from']->format('n/j/Y') }} – {{ $filters['to']->format('n/j/Y') }}</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Line discounts of <strong>{{ $money($profitability['line_discounts_included']) }}</strong> are already reflected in saved sales subtotals and are not deducted again.</div>
            <section class="mb-4"><span class="tf-dashboard-eyebrow">Net sales</span><div class="list-group list-group-flush border rounded"><div class="list-group-item d-flex justify-content-between"><span>Gross Sales</span><strong>{{ $money($profitability['gross_sales']) }}</strong></div><div class="list-group-item d-flex justify-content-between text-muted"><span>Sales Returns</span><strong>-{{ $money($profitability['sales_returns']) }}</strong></div><div class="list-group-item d-flex justify-content-between text-muted"><span>Invoice Discounts</span><strong>-{{ $money($profitability['invoice_discounts']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><strong>Net Sales</strong><strong>{{ $money($profitability['net_sales']) }}</strong></div></div><div class="d-flex gap-2 mt-2">@companyCan('sales.view')<a class="btn btn-sm btn-outline-primary" href="{{ route('business.sales.index') }}">View Sales</a>@endcompanyCan @companyCan('sales_returns.view')<a class="btn btn-sm btn-outline-primary" href="{{ route('business.sales.returns.index') }}">View Returns</a>@endcompanyCan</div></section>
            <section class="mb-4"><span class="tf-dashboard-eyebrow">Gross profit</span><div class="list-group list-group-flush border rounded"><div class="list-group-item d-flex justify-content-between"><span>Net Sales</span><strong>{{ $money($profitability['net_sales']) }}</strong></div><div class="list-group-item d-flex justify-content-between text-muted"><span>COGS</span><strong>-{{ $money($profitability['cogs']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><strong>Gross Profit</strong><strong class="{{ $profitability['gross_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($profitability['gross_profit']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>Gross Margin</span><strong>{{ $profitability['gross_margin'] === null ? '—' : number_format($profitability['gross_margin'], 2).'%' }}</strong></div></div><a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('business.reports.product-performance') }}">View Product Performance</a></section>
            <section class="mb-4"><span class="tf-dashboard-eyebrow">Highest COGS products</span><div class="table-responsive border rounded"><table class="table table-sm align-middle mb-0"><thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">COGS</th><th class="text-end">Share</th></tr></thead><tbody>@forelse($profitability['top_cogs_products'] as $product)<tr><td>{{ $product->name }}</td><td class="text-end">{{ $quantity($product->quantity) }}</td><td class="text-end">{{ $money($product->cogs) }}</td><td class="text-end">{{ number_format($product->share, 2) }}%</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-3">No COGS for this period.</td></tr>@endforelse</tbody></table></div></section>
            <section><span class="tf-dashboard-eyebrow">Operating expenses</span><div class="list-group list-group-flush border rounded">@forelse($profitability['expense_categories'] as $expense)<div class="list-group-item d-flex justify-content-between"><span>{{ $expense->category }}</span><strong>{{ $money($expense->amount) }}</strong></div>@empty<div class="list-group-item tf-muted">No operating expenses for this period.</div>@endforelse<div class="list-group-item d-flex justify-content-between"><strong>Total Expenses</strong><strong>{{ $money($profitability['expenses']) }}</strong></div></div></section>
            <section class="mt-4"><span class="tf-dashboard-eyebrow">Net profit / loss</span><div class="list-group list-group-flush border rounded"><div class="list-group-item d-flex justify-content-between"><span>Gross Profit</span><strong>{{ $money($profitability['gross_profit']) }}</strong></div><div class="list-group-item d-flex justify-content-between text-muted"><span>Operating Expenses</span><strong>-{{ $money($profitability['expenses']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><strong>{{ $profitability['net_profit'] < 0 ? 'Net Loss' : 'Net Profit' }}</strong><strong class="{{ $profitability['net_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($profitability['net_profit']) }}</strong></div></div></section>
        </div><div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@endif

<section class="tf-report-analytics" aria-labelledby="reportAnalyticsHeading">
    <div class="tf-report-section-title"><div><span>Analytics</span><h2 id="reportAnalyticsHeading">Performance trends</h2></div><p>Filtered activity over time.</p></div>
    <div class="tf-report-chart-grid">
        <article class="tf-report-chart-card tf-report-chart-card--wide"><header><div><h3>Sales Trend</h3><p>Net sales by period</p></div><span class="tf-report-legend"><i class="is-blue"></i>Net sales</span></header>
            @if($chartRows->isNotEmpty())
                <div class="tf-report-bars" role="img" aria-label="Net sales by reporting period">@foreach($chartRows as $row)<div class="tf-report-bar-column"><span title="{{ $row['label'] }}: {{ $money($row['net_sales']) }}" class="tf-report-bar is-blue" style="--tf-bar-height: {{ max(2, round(($row['net_sales'] / $salesChartMax) * 100)) }}%"></span><small>{{ $row['label'] }}</small></div>@endforeach</div>
            @else
                <div class="tf-report-empty"><i class="bi bi-bar-chart"></i><span>No sales data for this period.</span></div>
            @endif
        </article>
        @if($canViewFinanceReports && $canViewSalesAnalytics)
        <article class="tf-report-chart-card"><header><div><h3>Sales vs Expenses</h3><p>Net sales compared with expenses</p></div><span class="tf-report-legend"><i class="is-blue"></i>Sales <i class="is-amber"></i>Expenses</span></header>
            @if($chartRows->isNotEmpty())
                <div class="tf-report-bars">@foreach($chartRows as $row)<div class="tf-report-bar-column"><span title="{{ $row['label'] }} sales: {{ $money($row['net_sales']) }}" class="tf-report-bar is-blue" style="--tf-bar-height: {{ max(2, round(($row['net_sales'] / $comparisonChartMax) * 100)) }}%"></span><span title="{{ $row['label'] }} expenses: {{ $money($row['expenses']) }}" class="tf-report-bar is-amber" style="--tf-bar-height: {{ max(2, round(($row['expenses'] / $comparisonChartMax) * 100)) }}%"></span><small>{{ $row['label'] }}</small></div>@endforeach</div>
            @else
                <div class="tf-report-empty"><i class="bi bi-bar-chart"></i><span>No comparison data for this period.</span></div>
            @endif
        </article>
        @endif
        @if($canViewFinanceReports && $canViewSalesAnalytics)
        <article class="tf-report-chart-card"><header><div><h3>Profit Trend</h3><p>Gross and net profit</p></div><span class="tf-report-legend"><i class="is-green"></i>Gross <i class="is-blue"></i>Net</span></header>
            @if($chartRows->isNotEmpty())
                <div class="tf-report-bars">@foreach($chartRows as $row)<div class="tf-report-bar-column"><span title="{{ $row['label'] }} gross profit: {{ $money($row['gross_profit']) }}" class="tf-report-bar {{ $row['gross_profit'] < 0 ? 'is-red' : 'is-green' }}" style="--tf-bar-height: {{ max(2, round((abs($row['gross_profit']) / $profitChartMax) * 100)) }}%"></span><span title="{{ $row['label'] }} net profit: {{ $money($row['net_profit']) }}" class="tf-report-bar {{ $row['net_profit'] < 0 ? 'is-red' : 'is-blue' }}" style="--tf-bar-height: {{ max(2, round((abs($row['net_profit']) / $profitChartMax) * 100)) }}%"></span><small>{{ $row['label'] }}</small></div>@endforeach</div>
            @else
                <div class="tf-report-empty"><i class="bi bi-pie-chart"></i><span>No profit data for this period.</span></div>
            @endif
        </article>
        @endif
    </div>
</section>

<section class="tf-report-insights" aria-labelledby="reportInsightsHeading">
    <div class="tf-report-section-title"><div><span>Business intelligence</span><h2 id="reportInsightsHeading">Insights</h2></div><p>Actionable items from your live data.</p></div>
    <div class="tf-report-insight-grid">
        <article class="tf-report-insight-card"><header><span class="is-amber"><i class="bi bi-exclamation-triangle"></i></span><div><h3>Low Stock</h3><p>{{ $lowStockCount ? number_format($lowStockCount).' products need attention' : 'Inventory levels look healthy' }}</p></div></header>@forelse($lowStockProducts->take(1) as $product)<div class="tf-report-insight-detail"><strong>{{ $product->name }}</strong><span>{{ $quantity($product->stock_quantity) }} {{ $product->unit ?: '' }}</span></div>@empty<div class="tf-report-insight-empty">No low-stock products.</div>@endforelse</article>
        <article class="tf-report-insight-card"><header><span class="is-blue"><i class="bi bi-person-exclamation"></i></span><div><h3>Top Credit Customer</h3><p>Highest open customer balance</p></div></header>@forelse($topCustomers->take(1) as $customer)<div class="tf-report-insight-detail"><strong>{{ $customer->display_name }}</strong><span>{{ $money($customer->current_balance) }}</span></div>@empty<div class="tf-report-insight-empty">No outstanding customer balances.</div>@endforelse</article>
        <article class="tf-report-insight-card"><header><span class="is-orange"><i class="bi bi-building-exclamation"></i></span><div><h3>Supplier Exposure</h3><p>Highest supplier balance</p></div></header>@forelse($highestSupplierBalances->take(1) as $supplier)<div class="tf-report-insight-detail"><strong>{{ $supplier->supplier_name }}</strong><span>{{ $money($supplier->open_payable) }}</span></div>@empty<div class="tf-report-insight-empty">No supplier payables.</div>@endforelse</article>
        <article class="tf-report-insight-card"><header><span class="is-red"><i class="bi bi-calendar-x"></i></span><div><h3>Oldest Payable</h3><p>Earliest outstanding purchase</p></div></header>@forelse($oldestOutstandingPurchases->take(1) as $purchase)<div class="tf-report-insight-detail"><strong>{{ $purchase->purchase_number }}</strong><span>{{ $purchase->due_date?->isPast() ? $purchase->due_date->diffInDays(now()).' days overdue' : 'Due '.$purchase->due_date?->format('n/j/Y') }}</span></div>@empty<div class="tf-report-insight-empty">No outstanding purchases.</div>@endforelse</article>
    </div>
</section>

@companyCan('reports.export')
    @if($canViewSalesAnalytics || $canViewInventoryAnalytics || $canViewFinanceReports)
    <section class="tf-report-export" aria-label="Export reports">
        <div><span class="tf-dashboard-eyebrow">Export</span><h2>Export Report</h2><p>Generate a print-ready report for {{ $filters['from']->format('n/j/Y') }} &ndash; {{ $filters['to']->format('n/j/Y') }}.</p></div>
        <form method="GET" class="tf-report-export-form" data-report-export data-export-base="{{ url('/business/reports') }}">
            @foreach($exportFilters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <label>Report Type<select id="reportExportType" name="type" class="form-select">@if($canViewSalesAnalytics)<option value="sales">Sales</option>@endif @if($canViewInventoryAnalytics)<option value="inventory">Inventory</option>@endif @if($canViewFinanceReports)<option value="profit-loss">Profitability</option><option value="supplier-payables">Supplier Payables</option><option value="complete">Complete Business Report</option><option value="expense">Expenses</option>@endif</select></label>
            <label>Format<select id="reportExportFormat" name="format" class="form-select"><option value="pdf">PDF</option></select></label>
            <button class="btn btn-tf-primary" type="submit"><i class="bi bi-filetype-pdf"></i>Export Report</button>
        </form>
    </section>
    @endif
@endcompanyCan
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const period = document.querySelector('[data-report-period]');
    const customRange = document.querySelectorAll('.report-custom-range');
    const syncCustomRange = function () {
        const isCustom = period && period.value === 'custom';
        customRange.forEach(function (element) {
            element.classList.toggle('d-none', !isCustom);
            element.querySelectorAll('input').forEach(function (input) { input.disabled = !isCustom; });
        });
    };
    if (period) { period.addEventListener('change', syncCustomRange); syncCustomRange(); }

    const exportForm = document.querySelector('[data-report-export]');
    if (exportForm) {
        exportForm.addEventListener('submit', function () {
            exportForm.action = exportForm.dataset.exportBase + '/' + encodeURIComponent(exportForm.querySelector('[name="type"]').value) + '/pdf';
            exportForm.target = '_blank';
        });
    }
});
</script>
@endpush
