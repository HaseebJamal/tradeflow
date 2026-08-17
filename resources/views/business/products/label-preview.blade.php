@extends('layouts.dashboard')

@section('page-title', 'Barcode Label Preview')
@section('page-subtitle', 'Review labels before sending them to your printer')
@section('disable-dashboard-autofocus', 'true')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-label-print]')?.addEventListener('click', () => window.print());
});
</script>
@endpush

@section('content')
<div class="tf-label-preview-page">
    <div class="tf-label-preview-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h5 mb-1">{{ $format === 'thermal' ? 'Thermal' : 'A4 sheet' }} labels</h2>
            <p class="tf-muted mb-0">{{ count($labels) }} {{ count($labels) === 1 ? 'product' : 'products' }} · {{ $totalLabels }} {{ $totalLabels === 1 ? 'label' : 'labels' }} · {{ $priceType === 'none' ? 'No price' : ucfirst($priceType).' price' }}</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" type="button" onclick="window.history.back()"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <button class="btn btn-tf-primary" type="button" data-label-print><i class="bi bi-printer me-1"></i>Print</button>
        </div>
    </div>

    <section class="tf-label-sheet tf-label-sheet--{{ $format }}" aria-label="Barcode label preview">
        @foreach($labels as $label)
            @for($copy = 0; $copy < $label['quantity']; $copy++)
                <article class="tf-print-label">
                    @if($showBusinessName)
                        <div class="tf-print-label-business">{{ $business->business_name }}</div>
                    @endif
                    @if($showProductName)
                        <div class="tf-print-label-product">{{ $label['product']->name }}</div>
                    @endif
                    @if($showSku && filled($label['product']->sku))
                        <div class="tf-print-label-sku">SKU: {{ $label['product']->sku }}</div>
                    @endif
                    <div class="tf-print-label-barcode" aria-label="Barcode {{ $label['product']->barcode }}">{!! $label['barcode_svg'] !!}</div>
                    <div class="tf-print-label-code">{{ $label['product']->barcode }}</div>
                    @if($priceType !== 'none')
                        <div class="tf-print-label-price">{{ $priceType === 'retail' ? 'Retail' : 'Wholesale' }}: Rs {{ number_format($label['price'], 2) }}</div>
                    @endif
                </article>
            @endfor
        @endforeach
    </section>
</div>

<style media="print">
    @if($format === 'thermal')
        @page { size: 58mm 40mm; margin: 0; }
    @else
        @page { size: A4 portrait; margin: 10mm; }
    @endif
</style>
@endsection
