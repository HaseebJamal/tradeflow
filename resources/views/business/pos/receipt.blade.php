@extends('layouts.dashboard')
@section('page-title', 'Receipt '.($order->invoice?->invoice_number ?? $order->order_number))
@section('page-subtitle', 'Immutable receipt generated from the original sale.')
@section('content')
@php
    $receiptNumber = $order->invoice?->invoice_number ?? $order->order_number;
    $paper = (int) ($paper ?? 80) === 58 ? 58 : 80;
@endphp
<style>
    .tf-pos-receipt-preview {
        background: #f6f8fc;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        min-height: 32rem;
        padding: clamp(1rem, 3vw, 2rem);
    }
    .tf-pos-receipt-actions { margin-bottom: clamp(1.25rem, 3vw, 2rem); }
    .tf-pos-receipt-paper { display: flex; justify-content: center; }
    @media (max-width: 575.98px) {
        .tf-pos-receipt-preview { margin-inline: -0.25rem; padding: 1rem .5rem; }
        .tf-pos-receipt-actions .btn { flex: 1 1 auto; }
    }
</style>
<div class="tf-pos-receipt-preview">
    <div class="tf-pos-receipt-actions d-flex flex-wrap justify-content-end gap-2 d-print-none">
        <a class="btn btn-outline-secondary" href="{{ route('business.pos.history') }}"><i class="bi bi-arrow-left me-1"></i>Back to Sales History</a>
        <a class="btn btn-outline-secondary" href="{{ route('business.pos.index') }}"><i class="bi bi-plus-circle me-1"></i>New Sale</a>
        <a class="btn btn-outline-success" href="{{ route('business.pos.receipt.download', ['invoice' => $receiptNumber, 'paper' => $paper]) }}"><i class="bi bi-download me-1"></i>Download PDF</a>
        <a class="btn btn-tf-primary" href="{{ route('business.pos.receipt.print', ['invoice' => $receiptNumber, 'paper' => $paper]) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print Receipt</a>
    </div>
    <div class="tf-pos-receipt-paper">
        @include('business.pos._thermal-receipt', ['order' => $order, 'paper' => $paper])
    </div>
</div>
@endsection
