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
    $primaryMetrics = [
        ['Net Sales', $money($netSales), 'After discounts and returns', 'bi-receipt', 'blue'],
        ['Gross Profit', $money($grossProfit), 'Net sales less COGS', 'bi-graph-up-arrow', $grossProfit < 0 ? 'red' : 'green'],
        ['Outstanding Receivables', $money($outstandingReceivables), 'Open sales balance', 'bi-wallet2', 'amber'],
        ['Supplier Payables', $money($totalPayables), 'Open purchases in period', 'bi-building-exclamation', 'orange'],
        ['Net Profit / Loss', $money($netProfit), 'After operating expenses', 'bi-pie-chart', $netProfit < 0 ? 'red' : 'green'],
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
            ['Net Profit / Loss', $money($netProfit), 'Gross profit less expenses', 'bi-pie-chart', $netProfit < 0 ? 'red' : 'green'],
        ]],
        ['Supplier Exposure', 'Open supplier commitments', [
            ['Supplier Payables', $money($totalPayables), 'Open purchases in period', 'bi-building-exclamation', 'orange'],
            ['Due Today', $money($dueTodayPayables), 'Payment due today', 'bi-calendar-event', 'amber'],
            ['Due Soon', $money($dueSoonPayables), 'Due within seven days', 'bi-calendar-week', 'amber'],
            ['Overdue', $money($overduePayables), 'Past due date', 'bi-exclamation-circle', 'red'],
        ]],
    ];
@endphp

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

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
            <header><div><h2>{{ $title }}</h2><p>{{ $description }}</p></div></header>
            <div class="tf-report-mini-grid tf-report-mini-grid--{{ count($metrics) }}">
                @foreach($metrics as [$label, $value, $meta, $icon, $tone])
                    <div class="tf-report-mini-card"><span class="tf-report-mini-card__icon is-{{ $tone }}"><i class="bi {{ $icon }}"></i></span><div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $meta }}</small></div></div>
                @endforeach
            </div>
        </article>
    @endforeach
</section>

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
        <article class="tf-report-chart-card"><header><div><h3>Sales vs Expenses</h3><p>Net sales compared with expenses</p></div><span class="tf-report-legend"><i class="is-blue"></i>Sales <i class="is-amber"></i>Expenses</span></header>
            @if($chartRows->isNotEmpty())
                <div class="tf-report-bars">@foreach($chartRows as $row)<div class="tf-report-bar-column"><span title="{{ $row['label'] }} sales: {{ $money($row['net_sales']) }}" class="tf-report-bar is-blue" style="--tf-bar-height: {{ max(2, round(($row['net_sales'] / $comparisonChartMax) * 100)) }}%"></span><span title="{{ $row['label'] }} expenses: {{ $money($row['expenses']) }}" class="tf-report-bar is-amber" style="--tf-bar-height: {{ max(2, round(($row['expenses'] / $comparisonChartMax) * 100)) }}%"></span><small>{{ $row['label'] }}</small></div>@endforeach</div>
            @else
                <div class="tf-report-empty"><i class="bi bi-bar-chart"></i><span>No comparison data for this period.</span></div>
            @endif
        </article>
        <article class="tf-report-chart-card"><header><div><h3>Profit Trend</h3><p>Gross and net profit</p></div><span class="tf-report-legend"><i class="is-green"></i>Gross <i class="is-blue"></i>Net</span></header>
            @if($chartRows->isNotEmpty())
                <div class="tf-report-bars">@foreach($chartRows as $row)<div class="tf-report-bar-column"><span title="{{ $row['label'] }} gross profit: {{ $money($row['gross_profit']) }}" class="tf-report-bar {{ $row['gross_profit'] < 0 ? 'is-red' : 'is-green' }}" style="--tf-bar-height: {{ max(2, round((abs($row['gross_profit']) / $profitChartMax) * 100)) }}%"></span><span title="{{ $row['label'] }} net profit: {{ $money($row['net_profit']) }}" class="tf-report-bar {{ $row['net_profit'] < 0 ? 'is-red' : 'is-blue' }}" style="--tf-bar-height: {{ max(2, round((abs($row['net_profit']) / $profitChartMax) * 100)) }}%"></span><small>{{ $row['label'] }}</small></div>@endforeach</div>
            @else
                <div class="tf-report-empty"><i class="bi bi-pie-chart"></i><span>No profit data for this period.</span></div>
            @endif
        </article>
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
    <section class="tf-report-export" aria-label="Export reports">
        <div><span class="tf-dashboard-eyebrow">Export</span><h2>Export Report</h2><p>Generate a print-ready report for {{ $filters['from']->format('n/j/Y') }} &ndash; {{ $filters['to']->format('n/j/Y') }}.</p></div>
        <form method="GET" class="tf-report-export-form" data-report-export data-export-base="{{ url('/business/reports') }}">
            @foreach($exportFilters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <label>Report Type<select id="reportExportType" name="type" class="form-select"><option value="sales">Sales</option><option value="inventory">Inventory</option><option value="profit-loss">Profitability</option><option value="supplier-payables">Supplier Payables</option><option value="complete">Complete Business Report</option><option value="expense">Expenses</option></select></label>
            <label>Format<select id="reportExportFormat" name="format" class="form-select"><option value="pdf">PDF</option></select></label>
            <button class="btn btn-tf-primary" type="submit"><i class="bi bi-filetype-pdf"></i>Export Report</button>
        </form>
    </section>
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
