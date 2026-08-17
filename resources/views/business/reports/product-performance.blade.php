@extends('layouts.dashboard')
@section('page-title', 'Product Performance')
@section('page-subtitle', 'Revenue, gross profit, margins, and returns from completed sales')
@section('content')
@php($quantity = static fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0')
@php($money = static fn ($value) => 'Rs '.number_format((float) $value, 2))

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Product analytics</h2><p class="tf-muted mb-0">Uses actual completed-sale line totals, historical COGS snapshots, and recorded sales returns.</p></div>
    <div class="d-flex gap-2"><a href="{{ route('business.reports') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Reports</a>@if($canExport)<a target="_blank" rel="noopener" href="{{ route('business.reports.product-performance.pdf', request()->query()) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-pdf me-1"></i>Export PDF</a>@endif</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-2"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Net Product Sales</small><strong class="fs-5">{{ $money($summary['net_sales']) }}</strong></div></div>
    <div class="col-sm-6 col-xl-2"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Gross Profit</small><strong class="fs-5 {{ $summary['gross_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($summary['gross_profit']) }}</strong></div></div>
    <div class="col-sm-6 col-xl-2"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Average Margin</small><strong class="fs-5">{{ $summary['average_margin'] === null ? 'â€”' : number_format($summary['average_margin'], 2).'%' }}</strong></div></div>
    <div class="col-sm-6 col-xl-2"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Returned Value</small><strong class="fs-5 text-warning">{{ $money($summary['return_value']) }}</strong></div></div>
    <div class="col-sm-6 col-xl-2"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Overall Return Rate</small><strong class="fs-5">{{ $summary['return_rate'] === null ? 'â€”' : number_format($summary['return_rate'], 2).'%' }}</strong></div></div>
    <div class="col-sm-6 col-xl-2"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Loss-Making</small><strong class="fs-5 {{ $summary['loss_count'] ? 'text-danger' : '' }}">{{ number_format($summary['loss_count']) }}</strong></div></div>
</div>

<form method="GET" class="tf-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Search product</label><input name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Name or barcode"></div>
        <div class="col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($filters['category_id'] == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Unit</label><select name="unit_id" class="form-select"><option value="">All units</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($filters['unit_id'] == $unit->id)>{{ $unit->short_code ?: $unit->unit_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Period</label><select name="period" class="form-select">@foreach(['last_30'=>'Last 30 Days','last_60'=>'Last 60 Days','last_90'=>'Last 90 Days','this_month'=>'This Month','previous_month'=>'Previous Month','custom'=>'Custom Range'] as $key => $label)<option value="{{ $key }}" @selected($filters['period'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Performance type</label><select name="performance_type" class="form-select">@foreach(['all'=>'All','high-revenue'=>'High Revenue','high-profit'=>'High Profit','low-margin'=>'Low Margin','loss-making'=>'Loss-Making','high-return-rate'=>'High Return Rate'] as $key => $label)<option value="{{ $key }}" @selected($filters['performance_type'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date from</label><input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}"></div>
        <div class="col-md-2"><label class="form-label">Date to</label><input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}"></div>
        <div class="col-md-2"><label class="form-label">Sort by</label><select name="sort" class="form-select">@foreach(['auto'=>'Recommended','qty_sold'=>'Qty Sold','net_sales'=>'Net Sales','cogs'=>'COGS','gross_profit'=>'Gross Profit','gross_margin'=>'Margin %','returned_qty'=>'Returned Qty','return_value'=>'Return Value','return_rate'=>'Return Rate %'] as $key => $label)<option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-1"><label class="form-label">Rows</label><select name="per_page" class="form-select">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>@endforeach</select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-tf-primary flex-grow-1" type="submit">Filter</button><a href="{{ route('business.reports.product-performance') }}" class="btn btn-outline-primary">Clear</a></div>
    </div>
</form>

<div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i><strong>Method:</strong> Net sales are completed-sale line totals less proportional order discounts and refunds processed in the selected period. COGS uses each sale's historical purchase-cost snapshot less returned COGS. Return rate = returned quantity Ã· gross sold quantity. Low Margin is 10% or less; loss-making products are shown separately.</div>

@if(! $summary['has_activity'])
    <div class="tf-card p-5 text-center"><i class="bi bi-bar-chart-line fs-3 text-primary"></i><h3 class="h6 mt-3">No completed sales or recorded returns for this period.</h3><p class="tf-muted mb-0">Try a different period to view product performance.</p></div>
@else
    <div class="tf-card overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0 tf-business-data-table"><thead><tr><th>Product</th><th>Category</th><th>Qty Sold</th><th>Returned</th><th class="text-end">Net Sales</th><th class="text-end">COGS</th><th class="text-end">Gross Profit</th><th class="text-end">Margin</th><th class="text-end">Return Rate</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @foreach($analytics as $row)
            @php($statusClass = $row->status === 'Loss-Making' ? 'text-bg-danger' : ($row->status === 'Low Margin' ? 'text-bg-warning' : 'text-bg-success'))
            <tr>
                <td><strong>{{ $row->name }}</strong><small class="d-block tf-muted">{{ $row->unit }}</small></td>
                <td>{{ $row->category }}</td><td>{{ $quantity($row->qty_sold) }}</td><td>{{ $quantity($row->qty_returned) }}</td>
                <td class="text-end">{{ $money($row->net_sales) }}</td><td class="text-end">{{ $money($row->cogs) }}</td>
                <td class="text-end {{ $row->gross_profit < 0 ? 'text-danger' : '' }}">{{ $money($row->gross_profit) }}</td>
                <td class="text-end">{{ $row->gross_margin === null ? 'â€”' : number_format($row->gross_margin, 2).'%' }}</td>
                <td class="text-end">{{ $row->return_rate === null ? 'â€”' : number_format($row->return_rate, 2).'%' }}</td>
                <td>
                    <span class="badge {{ $statusClass }}">{{ $row->status }}</span>
                    @if($row->movement_status)<small class="d-block tf-muted">{{ $row->movement_status }}</small>@endif
                    @if($row->suggested_quantity > 0)<small class="d-block text-primary">Reorder: {{ $quantity($row->suggested_quantity) }}</small>@endif
                </td>
                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('business.reports.product-performance.show', array_merge(request()->query(), ['product' => $row->product_id])) }}">View</a></td>
            </tr>
        @endforeach
    </tbody></table></div></div>
    <div class="mt-3"><x-table-result-summary :paginator="$analytics" />{{ $analytics->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
