@extends('layouts.dashboard')

@section('page-title', 'End of Day')
@section('page-subtitle', 'Daily sales, cash reconciliation, purchasing, and profitability')

@section('content')
@php
    $money = static fn ($value) => 'Rs '.number_format((float) $value, 2);
    $profit = $report['profitability'];
    $sales = $report['sales'];
    $payments = $report['payments'];
    $registers = $report['registers'];
@endphp

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <form method="GET" class="d-flex flex-wrap align-items-end gap-2" aria-label="End of Day date filter">
        <div><label class="form-label small mb-1" for="endOfDayDate">Date</label><input id="endOfDayDate" class="form-control" type="date" name="date" value="{{ $selectedDate->toDateString() }}"></div>
        <button class="btn btn-outline-primary" type="submit">View</button>
    </form>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('business.reports') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Reports</a>
        @if($canExport)<a href="{{ route('business.reports.end-of-day.pdf', ['date' => $selectedDate->toDateString()]) }}" class="btn btn-tf-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>@endif
    </div>
</div>

<section class="tf-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"><div><span class="tf-dashboard-eyebrow">Daily closing</span><h2 class="h4 mb-1">{{ $selectedDate->format('n/j/Y') }}</h2><p class="tf-muted mb-0">All values use the application timezone and existing transaction records.</p></div><span class="tf-badge {{ $report['status'] === 'Reconciled' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $report['status'] }}</span></div>
    <div class="row g-3">
        @foreach([
            ['Net Sales', $money($sales['net_sales']), 'After invoice discounts and returns', 'bi-receipt', 'blue'],
            ['Payments Collected', $money($sales['paid_sales'] + $payments['customer_collections']), 'Sales payments plus prior receivable collections', 'bi-cash-stack', 'green'],
            ['Gross Profit', $money($profit['gross_profit']), 'Net sales less canonical COGS', 'bi-graph-up-arrow', $profit['gross_profit'] < 0 ? 'red' : 'green'],
            ['Expenses', $money($profit['expenses']), 'Operating expenses recorded today', 'bi-receipt-cutoff', 'orange'],
            [$profit['net_profit'] < 0 ? 'Net Loss' : 'Net Profit', $money($profit['net_profit']), 'Gross profit less expenses', 'bi-pie-chart', $profit['net_profit'] < 0 ? 'red' : 'green'],
        ] as [$label, $value, $help, $icon, $tone])
            <div class="col-sm-6 col-xl"><article class="tf-metric-card tf-metric-card--{{ $tone }} h-100"><div class="tf-metric-card__icon"><i class="bi {{ $icon }}"></i></div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $help }}</small></article></div>
        @endforeach
    </div>
</section>

<div class="row g-3 mb-3">
    <div class="col-lg-6"><section class="tf-card p-3 h-100"><span class="tf-dashboard-eyebrow">Sales</span><h2 class="h5 mb-3">Sales summary</h2><div class="list-group list-group-flush">
        <div class="list-group-item d-flex justify-content-between"><span>Invoices</span><strong>{{ number_format($sales['invoice_count']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Gross sales</span><strong>{{ $money($sales['gross_sales']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Line discounts <small class="text-muted">(included)</small></span><strong>{{ $money($sales['line_discounts_included']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Invoice discounts</span><strong>-{{ $money($sales['invoice_discounts']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Total discounts <small class="text-muted">(explanatory)</small></span><strong>{{ $money($sales['total_discounts']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Sales returns</span><strong>-{{ $money($sales['sales_returns']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><strong>Net sales</strong><strong>{{ $money($sales['net_sales']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Credit generated today</span><strong>{{ $money($sales['credit_sales']) }}</strong></div>
    </div></section></div>
    <div class="col-lg-6"><section class="tf-card p-3 h-100"><span class="tf-dashboard-eyebrow">Payments</span><h2 class="h5 mb-3">Payment method breakdown</h2><div class="list-group list-group-flush">
        @forelse($payments['breakdown'] as $method => $amount)<div class="list-group-item d-flex justify-content-between"><span>{{ $method }}</span><strong>{{ $money($amount) }}</strong></div>@empty<div class="text-muted py-3 text-center">No payments were applied to today's sales.</div>@endforelse
        <div class="list-group-item d-flex justify-content-between"><strong>Paid sales</strong><strong>{{ $money($sales['paid_sales']) }}</strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Customer collections</span><strong>{{ $money($payments['customer_collections']) }}</strong></div>
    </div><p class="small tf-muted mt-3 mb-0">Customer collections settle earlier receivables and are not counted as new sales revenue.</p></section></div>
</div>

@if($canPos && $registers)
<section class="tf-card p-3 mb-3"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><span class="tf-dashboard-eyebrow">Cash register</span><h2 class="h5 mb-1">Register reconciliation</h2><p class="tf-muted mb-0">Shifts opened on {{ $selectedDate->format('n/j/Y') }}.</p></div>@if($registers['open_count'])<span class="tf-badge tf-badge-warning">{{ $registers['open_count'] }} register {{ $registers['open_count'] === 1 ? 'still' : 's still' }} open</span>@endif</div>
    <div class="row g-2 mb-3">@foreach([['Opening cash','opening_cash'],['Cash sales','cash_sales'],['Cash refunds','cash_refunds'],['Cash in','cash_in'],['Cash out','cash_out'],['Expected closing','expected_cash']] as [$label,$key])<div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-2 h-100"><small class="tf-muted d-block">{{ $label }}</small><strong>{{ $money($registers[$key]) }}</strong></div></div>@endforeach</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cashier</th><th>Opened</th><th>Closed</th><th class="text-end">Expected</th><th class="text-end">Actual</th><th class="text-end">Variance</th><th>Status</th></tr></thead><tbody>@forelse($registers['rows'] as $shift)<tr><td>{{ $shift['cashier'] }}</td><td>{{ $shift['opened_at']?->format('g:i A') ?? '—' }}</td><td>{{ $shift['closed_at']?->format('g:i A') ?? 'Pending' }}</td><td class="text-end">{{ $money($shift['expected_cash']) }}</td><td class="text-end">{{ $shift['actual_cash'] === null ? 'Pending' : $money($shift['actual_cash']) }}</td><td class="text-end {{ ($shift['variance'] ?? 0) < 0 ? 'text-danger' : (($shift['variance'] ?? 0) > 0 ? 'text-warning' : 'text-success') }}">{{ $shift['variance'] === null ? '—' : $money(abs($shift['variance'])).($shift['variance'] < 0 ? ' shortage' : ($shift['variance'] > 0 ? ' excess' : '')) }}</td><td><span class="tf-badge {{ $shift['status'] === 'Closed' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $shift['status'] }}</span></td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-3">No POS register shifts opened on this date.</td></tr>@endforelse</tbody></table></div>
    @if($registers['open_count'])<div class="alert alert-warning small mb-0 mt-3">Daily cash reconciliation is incomplete because {{ $registers['open_count'] }} POS register {{ $registers['open_count'] === 1 ? 'remains' : 'remain' }} open. Expected cash is not confirmed actual cash.</div>@endif
</section>
@endif

<div class="row g-3 mb-3">
    @if($canPurchases && $report['purchases'])
    <div class="col-lg-6"><section class="tf-card p-3 h-100"><span class="tf-dashboard-eyebrow">Purchasing</span><h2 class="h5 mb-3">Purchases and supplier payments</h2><div class="list-group list-group-flush"><div class="list-group-item d-flex justify-content-between"><span>Purchases</span><strong>{{ $money($report['purchases']['amount']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>Paid purchase amount</span><strong>{{ $money($report['purchases']['paid_amount']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>Purchase returns / credits</span><strong>{{ $money($report['purchases']['purchase_returns']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>Supplier payments</span><strong>{{ $money($report['purchases']['supplier_payments']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>GRNs received</span><strong>{{ number_format($report['purchases']['grn_count']) }}</strong></div></div><p class="small tf-muted mt-3 mb-0">Purchase spend uses stored payable totals; free supplier quantities do not increase it.</p></section></div>
    @endif
    <div class="col-lg-{{ $canPurchases ? '6' : '12' }}"><section class="tf-card p-3 h-100"><span class="tf-dashboard-eyebrow">Profitability</span><h2 class="h5 mb-3">Daily profit waterfall</h2><div class="list-group list-group-flush"><div class="list-group-item d-flex justify-content-between"><span>Net sales</span><strong>{{ $money($profit['net_sales']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>COGS</span><strong>-{{ $money($profit['cogs']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><strong>Gross profit</strong><strong class="{{ $profit['gross_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($profit['gross_profit']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><span>Expenses</span><strong>-{{ $money($profit['expenses']) }}</strong></div><div class="list-group-item d-flex justify-content-between"><strong>{{ $profit['net_profit'] < 0 ? 'Net loss' : 'Net profit' }}</strong><strong class="{{ $profit['net_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($profit['net_profit']) }}</strong></div></div><a class="btn btn-sm btn-outline-primary mt-3" href="{{ route('business.reports', ['period' => 'custom', 'date_from' => $selectedDate->toDateString(), 'date_to' => $selectedDate->toDateString()]) }}">View Profit Breakdown</a></section></div>
</div>
@endsection
