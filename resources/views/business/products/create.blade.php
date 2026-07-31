@extends('layouts.dashboard')

@section('page-title', isset($product) ? 'Edit Product' : 'Add Products')
@section('page-subtitle', 'Create product identity, classification, and tracking information.')

@section('content')
@php
    $isEdit = isset($product);
    $draftProducts = old('products', [[]]);
    if (! is_array($draftProducts) || $draftProducts === []) {
        $draftProducts = [[]];
    }
@endphp

@if($errors->any())
    <div class="alert alert-danger" data-tf-persistent-alert data-product-create-errors role="alert">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($isEdit)
    <form method="POST" action="{{ route('business.products.update', $product) }}" enctype="multipart/form-data" data-inline-products-form data-inline-category-url="{{ route('business.categories.store') }}" data-inline-unit-url="{{ route('business.units.store') }}">
        @csrf
        @method('PUT')
        <section class="tf-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Product Information</h2>
                    <p class="tf-muted mb-0">Update the product identity, classification, and tracking information.</p>
                </div>
            </div>
            @include('business.products._master-fields', ['nested' => false, 'index' => 0, 'values' => $product])
            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-tf-primary" data-save-products>Save Product</button>
            </div>
        </section>
    </form>
@else
    <form method="POST" action="{{ route('business.products.store') }}" enctype="multipart/form-data" data-inline-products-form data-inline-category-url="{{ route('business.categories.store') }}" data-inline-unit-url="{{ route('business.units.store') }}">
        @csrf
        @include('business.products._multi-create-fields')
    </form>
@endif

@include('business.products._inline-catalog-modals')
@endsection

@push('scripts')
<script src="{{ asset('js/product-create-form.js') }}?v={{ filemtime(public_path('js/product-create-form.js')) }}"></script>
<script>document.addEventListener('DOMContentLoaded', () => window.initTradeFlowProductCreateForm?.(document));</script>
@endpush
