@extends('layouts.dashboard')
@section('page-title', 'Receipt '.($order->invoice?->invoice_number ?? $order->order_number))
@section('page-subtitle', 'Immutable receipt generated from the original sale.')
@section('content')
@php
    $receiptNumber = $order->invoice?->invoice_number ?? $order->order_number;
    $paper = (int) ($paper ?? 80) === 58 ? 58 : 80;
@endphp
<div class="tf-document-viewer tf-pos-receipt-preview">
    <div class="tf-document-viewer__toolbar tf-pos-receipt-actions d-flex flex-wrap justify-content-end gap-2 d-print-none">
        <a class="btn btn-outline-secondary" href="{{ route('business.pos.history') }}"><i class="bi bi-arrow-left me-1"></i>Back to Sales History</a>
        <a class="btn btn-outline-secondary" href="{{ route('business.pos.index') }}"><i class="bi bi-plus-circle me-1"></i>New Sale</a>
        <a class="btn btn-outline-success" href="{{ route('business.pos.receipt.download', ['invoice' => $receiptNumber, 'paper' => $paper]) }}"><i class="bi bi-download me-1"></i>Download PDF</a>
        <a class="btn btn-tf-primary" href="{{ route('business.pos.receipt.print', ['invoice' => $receiptNumber, 'paper' => $paper]) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print Receipt</a>
    </div>
    <div class="tf-document-viewer__paper tf-pos-receipt-paper">
        @include('business.pos._thermal-receipt', ['order' => $order, 'paper' => $paper])
    </div>
</div>
@endsection
