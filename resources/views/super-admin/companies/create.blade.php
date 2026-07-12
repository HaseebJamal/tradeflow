@extends('layouts.dashboard')
@section('page-title', 'Create Company')
@section('page-subtitle', 'Create a company and its owner account')
@section('content')
<div class="tf-card p-4">@include('super-admin.companies._form')</div>
@endsection
