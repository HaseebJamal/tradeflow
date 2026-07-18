@extends('layouts.dashboard')
@section('page-title', 'Unit Details')
@section('page-subtitle', 'Read-only unit information')
@section('content')
<div class="tf-card p-4" style="max-width:760px"><div class="row g-4"><div class="col-md-6"><div class="tf-muted small">Unit Name</div><strong>{{ $unit->unit_name }}</strong></div><div class="col-md-6"><div class="tf-muted small">Short Code</div><code>{{ $unit->short_code }}</code></div><div class="col-md-6"><div class="tf-muted small">Unit Type</div><span>{{ $unit->unit_type }}</span></div><div class="col-md-6"><div class="tf-muted small">Status</div><span class="tf-badge {{ $unit->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary' }}">{{ $unit->status }}</span></div><div class="col-12"><div class="tf-muted small">Description</div><span>{{ $unit->description ?: '—' }}</span></div><div class="col-md-6"><div class="tf-muted small">Created At</div><x-date-time :value="$unit->created_at" /></div></div><div class="mt-4"><a class="btn btn-outline-secondary" href="{{ route('business.units.index') }}">Back to Units</a></div></div>
@endsection
