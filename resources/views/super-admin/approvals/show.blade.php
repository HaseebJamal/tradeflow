@extends('layouts.dashboard')

@section('page-title', 'Approval Decision')
@section('page-subtitle', $log->company?->business_name ?: 'Company decision')

@section('content')
<div class="tf-card p-4"><div class="row g-3">@foreach(['Company' => $log->company?->business_name, 'Owner' => $log->company?->owner?->name, 'Old Status' => $log->old_status ? ucfirst($log->old_status) : '—', 'New Status' => ucfirst($log->new_status), 'Changed By' => $log->changedBy?->name ?: 'System / Public', 'Changed At' => optional($log->changed_at)->format('n/j/Y, g:i A')] as $label => $value)<div class="col-md-6"><div class="border rounded p-3"><small class="tf-muted">{{ $label }}</small><strong class="d-block">{{ $value ?: '—' }}</strong></div></div>@endforeach<div class="col-12"><div class="border rounded p-3"><small class="tf-muted">Decision Note</small><div>{{ $log->note ?: 'No note was recorded.' }}</div></div></div></div><div class="mt-4"><a href="{{ route('admin.approvals.history') }}" class="btn btn-outline-secondary">Back to History</a><a href="{{ route('admin.companies.show', $log->company_id) }}" class="btn btn-tf-primary">View Company</a></div></div>
@endsection
