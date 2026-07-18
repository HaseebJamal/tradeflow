@extends('layouts.dashboard')
@section('page-title', 'Category Details')
@section('page-subtitle', 'Read-only category information')
@section('content')
<div class="tf-card p-4" style="max-width:720px"><div class="row g-4"><div class="col-md-6"><div class="tf-muted small">Category</div><strong>{{ $category->name }}</strong></div><div class="col-md-6"><div class="tf-muted small">Status</div><span class="tf-badge {{ $category->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary' }}">{{ $category->status }}</span></div><div class="col-12"><div class="tf-muted small">Description</div>{{ $category->description ?: '—' }}</div><div class="col-md-6"><div class="tf-muted small">Created At</div><x-date-time :value="$category->created_at" /></div></div><div class="mt-4"><a class="btn btn-outline-secondary" href="{{ route('business.categories.index') }}">Back to Categories</a></div></div>
@endsection
