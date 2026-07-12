@extends('layouts.dashboard')
@section('page-title', 'Payments')
@section('page-subtitle', 'Manual payment records')
@section('content')
@companyCan('payments.create')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Record Payment</h2>
    <form method="POST" action="{{ route('business.payments.store') }}" enctype="multipart/form-data" class="row g-3">@csrf
        <div class="col-md-3"><select name="customer_id" class="form-select">@foreach($customers ?? [] as $customer)<option value="{{ $customer->id }}">{{ $customer->business_name ?: $customer->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="method" class="form-select">@foreach(['Cash','Bank Transfer','JazzCash manual','Easypaisa manual','Cheque'] as $method)<option>{{ $method }}</option>@endforeach</select></div>
        <div class="col-md-2"><input name="amount" type="number" class="form-control" placeholder="Amount"></div>
        <div class="col-md-2"><input name="payment_date" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
        <div class="col-md-2"><select name="status" class="form-select"><option>Paid</option><option>Partial</option><option>Pending</option></select></div>
        <div class="col-md-1"><button class="btn btn-tf-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
        <div class="col-md-3"><select name="order_id" class="form-select"><option value="">No order</option>@foreach($orders ?? [] as $order)<option value="{{ $order->id }}">{{ $order->order_number }}</option>@endforeach</select></div>
        <div class="col-md-3"><input name="reference_number" class="form-control" placeholder="Reference number"></div>
        <div class="col-md-6"><input name="proof_image" type="file" class="form-control"></div>
    </form>
</div>@endcompanyCan
<x-table><thead><tr><th>Payment</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead><tbody>@forelse($payments ?? [] as $payment)<tr><td>#PAY-{{ $payment->id }}</td><td>{{ $payment->customer?->business_name ?? $payment->customer?->name }}</td><td>{{ $payment->method }}</td><td>Rs {{ number_format($payment->amount) }}</td><td>{{ $payment->status }}</td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No payments yet.</td></tr>@endforelse</tbody></x-table>
@endsection
