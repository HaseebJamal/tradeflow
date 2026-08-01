@extends('layouts.dashboard')

@section('page-title', 'Business Report History')
@section('page-subtitle', 'Review previously generated and saved business reports')

@section('content')
    @php
        $statusBadge = fn (?string $status) => match (strtolower((string) $status)) {
            'verified', 'approved' => 'tf-badge-success',
            'pending', 'pending review' => 'tf-badge-warning',
            'rejected', 'cancelled' => 'tf-badge-danger',
            default => 'tf-badge-info',
        };
    @endphp

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a class="btn btn-outline-secondary" href="{{ route('admin.business-reports') }}">
            <i class="bi bi-arrow-left me-1"></i>Business Reports
        </a>
        <span class="tf-muted small">Saved reports are shown separately from live analytics.</span>
    </div>

    <div class="tf-card p-0 overflow-hidden">
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-1">Saved Business Reports</h2>
                <p class="tf-muted small mb-0">Historical report records and their approval status.</p>
            </div>
            @if(request()->hasAny(['business_id', 'report_type', 'status', 'date_from', 'date_to']))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.business-reports.history') }}">Clear filters</a>
            @endif
        </div>

        <x-table class="tf-business-data-table mb-0" style="min-width: 1180px">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Report Type</th>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Total Sales</th>
                    <th>Total Orders</th>
                    <th>Expenses</th>
                    <th>Profit</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td><strong>{{ $report->business?->business_name ?? 'Deleted business' }}</strong></td>
                        <td>{{ $report->report_type }}</td>
                        <td>{{ $report->month }}</td>
                        <td>{{ $report->year }}</td>
                        <td class="text-nowrap">Rs {{ number_format($report->total_sales) }}</td>
                        <td>{{ number_format($report->total_orders) }}</td>
                        <td class="text-nowrap">Rs {{ number_format($report->total_expense) }}</td>
                        <td class="text-nowrap">Rs {{ number_format($report->profit) }}</td>
                        <td><span class="tf-badge {{ $statusBadge($report->status) }}">{{ $report->status }}</span></td>
                        <td class="text-end text-nowrap">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">Actions</button>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                    @if($report->business)
                                        <a class="dropdown-item" href="{{ route('admin.business-reports.show', $report->business) }}"><i class="bi bi-eye me-2"></i>View Business</a>
                                    @endif
                                    <a class="dropdown-item" href="{{ route('admin.business-reports.edit', $report) }}"><i class="bi bi-pencil me-2"></i>Edit Metadata</a>
                                    <a class="dropdown-item" href="{{ route('admin.business-reports.pdf', $report) }}" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf me-2"></i>View PDF</a>
                                    <a class="dropdown-item" href="{{ route('admin.business-reports.pdf', ['report' => $report, 'download' => 1]) }}"><i class="bi bi-download me-2"></i>Download PDF</a>
                                    <a class="dropdown-item" href="{{ route('admin.business-reports.export.excel', array_merge(request()->query(), ['business_id' => $report->business_id, 'report_type' => $report->report_type])) }}"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export Excel</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center tf-muted py-5">
                            <i class="bi bi-clock-history d-block fs-3 mb-2"></i>
                            No saved business reports found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="p-3">
            <x-table-result-summary :paginator="$reports" />
            {{ $reports->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
