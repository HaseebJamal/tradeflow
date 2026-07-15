@extends('layouts.dashboard')
@section('title', 'Workspace Access | TradeFlow')
@section('page-title', 'Workspace Access')
@section('page-subtitle', 'Business module availability')
@section('content')
<div class="tf-card p-5 text-center">
    <i class="bi bi-shield-lock fs-2 text-warning"></i>
    <h2 class="h5 mt-3">No Business Modules Are Available</h2>
    <p class="tf-muted mb-0">Your Super Admin has not enabled a module that you can access. Please contact them to update your company permissions.</p>
</div>
@endsection
