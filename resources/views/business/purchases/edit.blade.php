@extends('layouts.dashboard')
@section('page-title', 'Edit Purchase '.$purchase->purchase_number)
@section('page-subtitle', 'Update the draft before confirming it')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@include('business.purchases._form')
@endsection
