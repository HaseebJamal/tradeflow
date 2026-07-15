@extends('layouts.dashboard')
@section('page-title', 'Purchase Return Workflow')
@section('page-subtitle', $return->return_number)
@section('content')
<section class="tf-card p-4"><h2 class="h5">Posted return</h2><p class="tf-muted mb-3">This return is read-only because it already updated inventory, supplier balances, and accounting. Create a new return or stock adjustment for any correction.</p><a href="{{ route('business.purchase-returns.show', $return) }}" class="btn btn-outline-primary">View return</a></section>
@endsection
