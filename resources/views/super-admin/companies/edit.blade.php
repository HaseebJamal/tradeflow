@extends('layouts.dashboard')
@section('page-title', 'Edit Company')
@section('page-subtitle', $company->business_name)
@section('content')
<div class="tf-card p-4">@include('super-admin.companies._form')</div>
@endsection
