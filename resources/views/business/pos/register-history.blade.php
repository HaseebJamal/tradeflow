@extends('layouts.dashboard')

@section('page-title', 'Register Shift History')
@section('page-subtitle', 'Review cashier opening, reconciliation, and closing results')

@section('content')
<div class="tf-module-page">
    <header class="tf-module-header">
        <div><span class="tf-dashboard-eyebrow">POS CONTROL</span><h2>Register Shift History</h2><p>Each completed shift retains its cash reconciliation and variance.</p></div>
        <a href="{{ route('business.pos.index') }}" class="btn btn-outline-primary tf-module-secondary-action"><i class="bi bi-arrow-left"></i>Back to POS</a>
    </header>

    <section class="tf-module-table-card">
        <div class="table-responsive tf-dropdown-safe-scroll">
            <table class="table tf-admin-data-table mb-0">
                <thead><tr><th>Cashier</th><th>Opened</th><th>Opening</th><th>Cash Sales</th><th>Refunds</th><th>Cash In</th><th>Cash Out</th><th>Expected</th><th>Actual</th><th>Variance</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($registers as $register)
                        @php($variance = $register->variance)
                        <tr>
                            <td class="fw-semibold">{{ $register->user?->name ?? '—' }}</td>
                            <td class="text-nowrap">{{ $register->opened_at?->format('n/j/Y, g:i A') ?? '—' }}</td>
                            <td class="text-nowrap">Rs {{ number_format((float) $register->opening_cash, 2) }}</td>
                            <td class="text-nowrap">Rs {{ number_format((float) $register->cash_sales, 2) }}</td>
                            <td class="text-nowrap">Rs {{ number_format((float) $register->cash_refunds, 2) }}</td>
                            <td class="text-nowrap">Rs {{ number_format((float) $register->cash_in, 2) }}</td>
                            <td class="text-nowrap">Rs {{ number_format((float) $register->cash_out, 2) }}</td>
                            <td class="text-nowrap fw-semibold">{{ $register->expected_cash === null ? '—' : 'Rs '.number_format((float) $register->expected_cash, 2) }}</td>
                            <td class="text-nowrap">{{ $register->closing_cash === null ? '—' : 'Rs '.number_format((float) $register->closing_cash, 2) }}</td>
                            <td class="text-nowrap">@if($variance === null)—@elseif($variance > 0)<span class="text-success fw-semibold">Excess Rs {{ number_format((float) $variance, 2) }}</span>@elseif($variance < 0)<span class="text-danger fw-semibold">Short Rs {{ number_format(abs((float) $variance), 2) }}</span>@else<span class="text-success fw-semibold">Balanced</span>@endif</td>
                            <td><span class="tf-badge {{ $register->status === 'Open' ? 'tf-badge-warning' : 'tf-badge-success' }}">{{ $register->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center tf-muted py-5">No register shifts have been opened yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $registers->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection
