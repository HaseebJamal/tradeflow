@extends('layouts.dashboard')
@section('page-title', 'Sales Return Workflow')
@section('page-subtitle', $return->order?->order_number)
@section('content')
<section class="tf-card p-4"><h2 class="h5">Posted return</h2><p class="tf-muted mb-3">This return is read-only because inventory, customer balances, payment records, and accounting have already been updated. Use a new return for any additional items.</p><a href="{{ route('business.sales.returns.show', $return) }}" class="btn btn-outline-primary">View return</a></section>
@endsection
