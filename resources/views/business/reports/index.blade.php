@extends('layouts.dashboard')

@section('page-title', 'Business Reports')
@section('page-subtitle', 'Sales, inventory, profitability, and payable analytics')

@section('content')
@php
    $money = static function ($value): string {
        $amount = (float) $value;
        return 'Rs '.number_format($amount, floor($amount) === $amount ? 0 : 2);
    };
    $quantity = static function ($value): string {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    };
    $chartRows = collect($chartSeries ?? [])->take(-12);
    $salesChartMax = max(1, (float) $chartRows->max('net_sales'));
    $comparisonChartMax = max(1, (float) $chartRows->flatMap(fn ($row) => [$row['net_sales'], $row['expenses']])->max());
    $profitChartMax = max(1, (float) $chartRows->flatMap(fn ($row) => [abs($row['gross_profit']), abs($row['net_profit'])])->max());
@endphp

<style>
    .report-period-label { color: var(--tf-muted, #64748b); font-size: .8125rem; }
    .report-section-heading { margin: 1.5rem 0 .75rem; font-size: 1rem; font-weight: 700; }
    .report-kpi { height: 100%; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; padding: 1rem; }
    .report-kpi-label { color: #64748b; font-size: .8125rem; margin-bottom: .35rem; }
    .report-kpi-value { color: #172033; font-size: 1.25rem; font-weight: 700; line-height: 1.2; overflow-wrap: anywhere; }
    .report-kpi-meta { color: #64748b; font-size: .75rem; margin-top: .45rem; }
    .report-chart { min-height: 230px; }
    .report-bars { display: flex; align-items: flex-end; gap: .5rem; height: 172px; padding-top: .75rem; border-bottom: 1px solid #cbd5e1; overflow-x: auto; }
    .report-bar-group { min-width: 34px; flex: 1 0 34px; height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 3px; }
    .report-bar { width: 12px; min-height: 2px; background: #2563eb; border-radius: 3px 3px 0 0; }
    .report-bar.is-expense { background: #f59e0b; }
    .report-bar.is-gross-profit { background: #0f766e; }
    .report-bar.is-net-profit { background: #2563eb; }
    .report-bar.is-negative { background: #dc2626; }
    .report-chart-labels { display: flex; gap: .5rem; overflow-x: auto; padding-top: .45rem; }
    .report-chart-label { min-width: 34px; flex: 1 0 34px; color: #64748b; font-size: .6875rem; text-align: center; white-space: nowrap; }
    .report-legend { color: #64748b; display: flex; flex-wrap: wrap; gap: 1rem; font-size: .75rem; }
    .report-legend-dot { display: inline-block; width: .625rem; height: .625rem; margin-right: .3rem; border-radius: 50%; }
    .report-insight-row:last-child { border-bottom: 0 !important; }
    @media (max-width: 767.98px) { .report-kpi { padding: .875rem; } .report-kpi-value { font-size: 1.1rem; } }
</style>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="GET" class="tf-card p-3 mb-3" id="reportFilters">
    <div class="row g-3 align-items-end">
        <div class="col-md-4 col-xl-3">
            <label class="form-label" for="reportPeriod">Period</label>
            <select name="period" id="reportPeriod" class="form-select" data-report-period>
                <option value="today" @selected($filters['period'] === 'today')>Today</option>
                <option value="this_week" @selected($filters['period'] === 'this_week')>This Week</option>
                <option value="this_month" @selected($filters['period'] === 'this_month')>This Month</option>
                <option value="this_year" @selected($filters['period'] === 'this_year')>This Year</option>
                <option value="custom" @selected($filters['period'] === 'custom')>Custom Range</option>
            </select>
        </div>
        <div class="col-md-4 col-xl-3 report-custom-range {{ $filters['period'] === 'custom' ? '' : 'd-none' }}">
            <label class="form-label" for="reportDateFrom">Date From</label>
            <input name="date_from" id="reportDateFrom" type="date" value="{{ $filters['from']->toDateString() }}" class="form-control" {{ $filters['period'] === 'custom' ? '' : 'disabled' }}>
        </div>
        <div class="col-md-4 col-xl-3 report-custom-range {{ $filters['period'] === 'custom' ? '' : 'd-none' }}">
            <label class="form-label" for="reportDateTo">Date To</label>
            <input name="date_to" id="reportDateTo" type="date" value="{{ $filters['to']->toDateString() }}" class="form-control" {{ $filters['period'] === 'custom' ? '' : 'disabled' }}>
        </div>
        <div class="col-md-4 col-xl-3">
            <label class="form-label" for="reportStatus">Status</label>
            <select name="status" id="reportStatus" class="form-select">
                <option value="">All Statuses</option>
                @foreach(['New', 'Pending', 'Accepted', 'Packing', 'Ready', 'Delivered', 'Completed', 'Partially Returned', 'Returned', 'Cancelled'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-3">
            <label class="form-label" for="reportCustomer">Customer</label>
            <select name="customer_id" id="reportCustomer" class="form-select">
                <option value="">All Customers</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->business_name ?: $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-3">
            <label class="form-label" for="reportProduct">Product</label>
            <select name="product_id" id="reportProduct" class="form-select">
                <option value="">All Products</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-xl-3 d-flex gap-2">
            <button class="btn btn-outline-primary flex-fill" type="submit">Filter</button>
            <a href="{{ route('business.reports') }}" class="btn btn-outline-secondary flex-fill">Clear</a>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
    <div>
        <h2 class="h5 mb-1">Report overview</h2>
        <div class="report-period-label">{{ $filters['label'] }}: {{ $filters['from']->format('n/j/Y') }} to {{ $filters['to']->format('n/j/Y') }}</div>
    </div>
</div>

<h2 class="report-section-heading">Sales</h2>
<div class="row g-3">
    @foreach([
        ['Gross Sales', $money($grossSales), 'Before discounts and returns'],
        ['Discounts', $money($salesDiscounts), 'Applied to selected sales'],
        ['Net Sales', $money($netSales), 'After discounts and returns'],
        ['Revenue Received', $money($revenueReceived), 'Payments received in period'],
        ['Outstanding Receivables', $money($outstandingReceivables), 'Open selected sales balance'],
    ] as [$label, $value, $meta])
        <div class="col-12 col-sm-6 col-lg"><div class="report-kpi"><div class="report-kpi-label">{{ $label }}</div><div class="report-kpi-value">{{ $value }}</div><div class="report-kpi-meta">{{ $meta }}</div></div></div>
    @endforeach
</div>

<h2 class="report-section-heading">Orders and inventory</h2>
<div class="row g-3">
    @foreach([
        ['Completed Orders', number_format($completedOrders), 'Selected period'],
        ['Pending Orders', number_format($pendingOrders), 'Selected period'],
        ['Stock Value', $money($stockValue), 'Current inventory'],
        ['Low Stock', number_format($lowStockCount), 'Products above zero'],
        ['Out of Stock', number_format($outOfStockCount), 'Quantity is zero'],
    ] as [$label, $value, $meta])
        <div class="col-12 col-sm-6 col-lg"><div class="report-kpi"><div class="report-kpi-label">{{ $label }}</div><div class="report-kpi-value">{{ $value }}</div><div class="report-kpi-meta">{{ $meta }}</div></div></div>
    @endforeach
</div>

<h2 class="report-section-heading">Profitability</h2>
<div class="row g-3">
    @foreach([
        ['COGS', $money($cogs), 'Cost of goods sold'],
        ['Gross Profit', $money($grossProfit), 'Net sales less COGS'],
        ['Expenses', $money($expenses), 'Operating expenses'],
        ['Net Profit / Loss', $money($netProfit), 'Gross profit less expenses'],
    ] as [$label, $value, $meta])
        <div class="col-12 col-sm-6 col-lg-3"><div class="report-kpi"><div class="report-kpi-label">{{ $label }}</div><div class="report-kpi-value">{{ $value }}</div><div class="report-kpi-meta">{{ $meta }}</div></div></div>
    @endforeach
</div>

<h2 class="report-section-heading">Supplier payables</h2>
<div class="row g-3">
    @foreach([
        ['Supplier Payables', $money($totalPayables), 'Open purchases in period'],
        ['Due Today', $money($dueTodayPayables), 'Payment due today'],
        ['Due Soon', $money($dueSoonPayables), 'Due within seven days'],
        ['Overdue', $money($overduePayables), 'Past due date'],
    ] as [$label, $value, $meta])
        <div class="col-12 col-sm-6 col-lg-3"><div class="report-kpi"><div class="report-kpi-label">{{ $label }}</div><div class="report-kpi-value">{{ $value }}</div><div class="report-kpi-meta">{{ $meta }}</div></div></div>
    @endforeach
</div>

<h2 class="report-section-heading">Analytics</h2>
<div class="row g-3">
    <div class="col-xl-4"><div class="tf-card p-3 report-chart"><h3 class="h6 mb-1">Sales trend</h3><p class="report-period-label mb-2">Net sales by period</p>@if($chartRows->isNotEmpty())<div class="report-bars">@foreach($chartRows as $row)<div class="report-bar-group"><span class="report-bar" style="height: {{ max(2, round(($row['net_sales'] / $salesChartMax) * 100)) }}%" title="{{ $row['label'] }}: {{ $money($row['net_sales']) }}"></span></div>@endforeach</div><div class="report-chart-labels">@foreach($chartRows as $row)<span class="report-chart-label">{{ $row['label'] }}</span>@endforeach</div>@else<div class="d-flex align-items-center justify-content-center h-75 text-muted small">No sales data for this period.</div>@endif</div></div>
    <div class="col-xl-4"><div class="tf-card p-3 report-chart"><h3 class="h6 mb-1">Sales and expenses</h3><p class="report-period-label mb-2">Net sales compared with expenses</p><div class="report-legend"><span><i class="report-legend-dot bg-primary"></i>Net sales</span><span><i class="report-legend-dot" style="background:#f59e0b"></i>Expenses</span></div>@if($chartRows->isNotEmpty())<div class="report-bars">@foreach($chartRows as $row)<div class="report-bar-group"><span class="report-bar" style="height: {{ max(2, round(($row['net_sales'] / $comparisonChartMax) * 100)) }}%" title="{{ $row['label'] }} net sales: {{ $money($row['net_sales']) }}"></span><span class="report-bar is-expense" style="height: {{ max(2, round(($row['expenses'] / $comparisonChartMax) * 100)) }}%" title="{{ $row['label'] }} expenses: {{ $money($row['expenses']) }}"></span></div>@endforeach</div><div class="report-chart-labels">@foreach($chartRows as $row)<span class="report-chart-label">{{ $row['label'] }}</span>@endforeach</div>@else<div class="d-flex align-items-center justify-content-center h-75 text-muted small">No comparison data for this period.</div>@endif</div></div>
    <div class="col-xl-4"><div class="tf-card p-3 report-chart"><h3 class="h6 mb-1">Profit trend</h3><p class="report-period-label mb-2">Gross profit and net profit</p><div class="report-legend"><span><i class="report-legend-dot" style="background:#0f766e"></i>Gross profit</span><span><i class="report-legend-dot bg-primary"></i>Net profit</span></div>@if($chartRows->isNotEmpty())<div class="report-bars">@foreach($chartRows as $row)<div class="report-bar-group"><span class="report-bar {{ $row['gross_profit'] < 0 ? 'is-negative' : 'is-gross-profit' }}" style="height: {{ max(2, round((abs($row['gross_profit']) / $profitChartMax) * 100)) }}%" title="{{ $row['label'] }} gross profit: {{ $money($row['gross_profit']) }}"></span><span class="report-bar {{ $row['net_profit'] < 0 ? 'is-negative' : 'is-net-profit' }}" style="height: {{ max(2, round((abs($row['net_profit']) / $profitChartMax) * 100)) }}%" title="{{ $row['label'] }} net profit: {{ $money($row['net_profit']) }}"></span></div>@endforeach</div><div class="report-chart-labels">@foreach($chartRows as $row)<span class="report-chart-label">{{ $row['label'] }}</span>@endforeach</div>@else<div class="d-flex align-items-center justify-content-center h-75 text-muted small">No profit data for this period.</div>@endif</div></div>
</div>

<h2 class="report-section-heading">Insights</h2>
<div class="row g-3 mb-4">
    <div class="col-lg-6 col-xl-3"><div class="tf-card p-3 h-100"><h3 class="h6">Low stock products</h3>@forelse($lowStockProducts as $product)<div class="report-insight-row border-bottom py-2 d-flex justify-content-between gap-2"><span>{{ $product->name }}</span><span class="text-nowrap">{{ $quantity($product->stock_quantity) }} <span class="badge {{ (float) $product->stock_quantity <= 0 ? 'text-bg-danger' : 'text-bg-warning' }}">{{ (float) $product->stock_quantity <= 0 ? 'Out of stock' : 'Low stock' }}</span></span></div>@empty<p class="text-muted small mb-0">No low-stock products.</p>@endforelse</div></div>
    <div class="col-lg-6 col-xl-3"><div class="tf-card p-3 h-100"><h3 class="h6">Top credit customers</h3>@forelse($topCustomers as $customer)<div class="report-insight-row border-bottom py-2 d-flex justify-content-between gap-2"><span>{{ $customer->display_name }}</span><span class="text-nowrap">{{ $money($customer->current_balance) }}</span></div>@empty<p class="text-muted small mb-0">No outstanding customer balances.</p>@endforelse</div></div>
    <div class="col-lg-6 col-xl-3"><div class="tf-card p-3 h-100"><h3 class="h6">Highest supplier balances</h3>@forelse($highestSupplierBalances as $supplier)<div class="report-insight-row border-bottom py-2 d-flex justify-content-between gap-2"><span>{{ $supplier->supplier_name }}</span><span class="text-nowrap">{{ $money($supplier->open_payable) }}</span></div>@empty<p class="text-muted small mb-0">No supplier payables.</p>@endforelse</div></div>
    <div class="col-lg-6 col-xl-3"><div class="tf-card p-3 h-100"><h3 class="h6">Oldest outstanding purchases</h3>@forelse($oldestOutstandingPurchases as $purchase)<div class="report-insight-row border-bottom py-2"><div class="d-flex justify-content-between gap-2"><span>{{ $purchase->purchase_number }}</span><span class="text-nowrap">{{ $money($purchase->balance) }}</span></div><small class="text-muted d-block">{{ $purchase->supplier?->supplier_name }}@if($purchase->due_date) &middot; {{ $purchase->due_date->isPast() ? $purchase->due_date->diffInDays(now()).' days overdue' : 'Due '.$purchase->due_date->format('n/j/Y') }}@endif</small></div>@empty<p class="text-muted small mb-0">No outstanding purchases.</p>@endforelse</div></div>
</div>

@companyCan('reports.export')
    <div class="tf-card p-3 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div><h2 class="h6 mb-1">Export reports</h2><p class="report-period-label mb-0">Export the currently filtered reporting period.</p></div>
            <form method="GET" class="d-flex gap-2 align-items-end" data-report-export data-export-base="{{ url('/business/reports') }}">
                @foreach($exportFilters as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <div>
                    <label class="form-label mb-1" for="reportExportType">Report</label>
                    <select id="reportExportType" name="type" class="form-select form-select-sm">
                        <option value="sales">Sales</option>
                        <option value="inventory">Inventory</option>
                        <option value="expense">Expenses</option>
                        <option value="profit-loss">Profit and Loss</option>
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1" for="reportExportFormat">Format</label>
                    <select id="reportExportFormat" name="format" class="form-select form-select-sm"><option value="pdf">PDF</option></select>
                </div>
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-filetype-pdf me-1"></i>Export</button>
            </form>
        </div>
    </div>
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
    if (period) {
        period.addEventListener('change', syncCustomRange);
        syncCustomRange();
    }

    const exportForm = document.querySelector('[data-report-export]');
    if (exportForm && !exportForm.dataset.bound) {
        exportForm.dataset.bound = 'true';
        exportForm.addEventListener('submit', function () {
            const type = exportForm.querySelector('[name="type"]').value;
            exportForm.action = exportForm.dataset.exportBase + '/' + encodeURIComponent(type) + '/pdf';
            exportForm.target = '_blank';
        });
    }
});
</script>
@endpush
