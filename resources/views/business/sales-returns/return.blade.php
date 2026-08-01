@extends('layouts.dashboard')
@section('page-title', 'Sales Return')
@section('page-subtitle', 'Select the items and quantities to return from sale '.$order->order_number)
@section('content')
    @include('business.sales-returns._return-form', ['order' => $order])
@endsection
