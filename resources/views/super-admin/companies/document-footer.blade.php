@extends('layouts.dashboard')
@section('page-title', 'Company Receipt Footer')
@section('page-subtitle', $company->business_name)
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="mb-4"><h2 class="h5 mb-1">Receipt Footer</h2><p class="tf-muted mb-0">Manage the footer for this selected company only. Changes apply to future printable documents.</p></div>
<x-document-footer-settings-form :business="$company" :footer="$footer" :action="route('admin.companies.document-footer.update', $company)" :back-route="route('admin.companies.show', $company)" :reset-action="route('admin.companies.document-footer.reset', $company)" :locked-company="true" />
@endsection
