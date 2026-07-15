@extends('layouts.dashboard')
@section('page-title', 'New Purchase Order')
@section('page-subtitle', 'Create a supplier purchase order; inventory updates when goods are received')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@include('business.purchases._form')
@endsection
