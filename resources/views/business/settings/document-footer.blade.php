@extends('layouts.dashboard')
@section('page-title', 'Receipt Footer')
@section('page-subtitle', 'Branding & Documents')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="mb-4"><h2 class="h5 mb-1">Receipt Footer</h2><p class="tf-muted mb-0">These settings apply to future receipts, invoices, and printable documents for {{ $business->business_name }}.</p></div>
<x-document-footer-settings-form :business="$business" :footer="$footer" :action="route('business.settings.document-footer.update')" :back-route="route('business.settings')" />
@endsection
