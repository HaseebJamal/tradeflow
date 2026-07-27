@extends('layouts.dashboard')
@section('page-title', 'POS Receipt')
@section('page-subtitle', $order->invoice?->invoice_number ?? $order->order_number)
@section('content')
@php
    $receiptNumber = $order->invoice?->invoice_number ?? $order->order_number;
    $paper = (int) ($paper ?? 80) === 58 ? 58 : 80;
@endphp
<div class="d-flex flex-wrap justify-content-end gap-2 mb-3 d-print-none">
    <a class="btn btn-outline-secondary" href="{{ route('business.pos.history') }}"><i class="bi bi-arrow-left me-1"></i>Back to Sales History</a>
    <a class="btn btn-outline-secondary" href="{{ route('business.pos.index') }}"><i class="bi bi-plus-circle me-1"></i>New Sale</a>
    <a class="btn btn-outline-success" href="{{ route('business.pos.receipt.download', ['invoice' => $receiptNumber, 'paper' => $paper]) }}"><i class="bi bi-download me-1"></i>Download PDF</a>
    <a class="btn btn-tf-primary" href="{{ route('business.pos.receipt.print', ['invoice' => $receiptNumber, 'paper' => $paper]) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print Receipt</a>
</div>

@include('business.pos._thermal-receipt', ['order' => $order, 'paper' => $paper])
@endsection
