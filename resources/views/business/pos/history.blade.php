@extends('layouts.dashboard')

@section('page-title', 'POS Sales History')
@section('page-subtitle', 'Completed counter sales, receipts, and returns')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h5 mb-0">Sales Directory</h2>
        <a href="{{ route('business.pos.index') }}" class="btn btn-tf-primary">
            <i class="bi bi-upc-scan me-1"></i>New Sale
        </a>
    </div>

    <div class="tf-card p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="pos-history-search">Receipt Number</label>
                <input id="pos-history-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search receipt">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="pos-history-from">Date From</label>
                <input id="pos-history-from" name="date_from" type="date" class="form-control" value="{{ request('date_from', now()->startOfMonth()->toDateString()) }}">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="pos-history-to">Date To</label>
                <input id="pos-history-to" name="date_to" type="date" class="form-control" value="{{ request('date_to', now()->toDateString()) }}">
            </div>
            <div class="col-sm-6 col-lg-3 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill">Filter</button>
                <a href="{{ route('business.pos.history') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <x-table>
        <thead>
            <tr>
                <th>Receipt</th>
                <th>Customer</th>
                <th>Date & Time</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                @php
                    $statusClass = match(strtolower($order->status)) {
                        'completed', 'paid' => 'tf-badge-success',
                        'returned', 'void', 'cancelled' => 'tf-badge-danger',
                        default => 'tf-badge-warning',
                    };
                @endphp
                <tr>
                    <td><strong>{{ $order->order_number }}</strong></td>
                    <td>{{ $order->customer?->display_name ?? 'Walk-in Customer' }}</td>
                    <td><x-date-time :value="$order->order_date" /></td>
                    <td>Rs {{ number_format($order->grand_total, 2) }}</td>
                    <td>Rs {{ number_format($order->paid_amount, 2) }}</td>
                    <td><span class="tf-badge {{ $statusClass }}">{{ $order->status }}</span></td>
                    <td>
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('business.pos.receipt', $order) }}">View</a>

                            @companyCan('pos.print_receipt')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('business.pos.receipt.pdf', $order) }}" target="_blank" rel="noopener">PDF</a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('business.pos.receipt.pdf.download', $order) }}">Download</a>
                            @endcompanyCan

                            @if(in_array($order->status, ['New', 'Accepted'], true))
                                <a class="btn btn-sm btn-outline-warning" href="{{ route('business.sales.edit', $order) }}">Edit Draft</a>

                                @companyCan('pos.void_sale')
                                    <form method="POST" action="{{ route('business.pos.void', $order) }}" onsubmit="return confirm('Void this unpaid POS draft and restore stock?')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-outline-danger">Void</button>
                                    </form>
                                @endcompanyCan
                            @endif

                            @if(!in_array($order->status, ['Void', 'Cancelled'], true))
                                @companyCan('sales_returns.process')
                                    <a class="btn btn-sm btn-outline-warning" href="{{ route('business.pos.returns', $order) }}">Return</a>
                                @endcompanyCan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center tf-muted py-4">No POS sales found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
