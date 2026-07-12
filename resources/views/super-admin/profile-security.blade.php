@extends('layouts.dashboard')
@section('page-title', 'Account Security')
@section('page-subtitle', 'Review your Super Admin account security')
@section('content')
<div class="row g-4"><div class="col-lg-6"><div class="tf-card p-4"><h2 class="h5">Account Status</h2><dl class="row mb-0"><dt class="col-sm-5">Role</dt><dd class="col-sm-7">{{ auth()->user()->role }}</dd><dt class="col-sm-5">Account Status</dt><dd class="col-sm-7">{{ auth()->user()->status }}</dd><dt class="col-sm-5">Last Seen</dt><dd class="col-sm-7"><x-date-time :value="auth()->user()->last_seen_at" /></dd><dt class="col-sm-5">Last Activity</dt><dd class="col-sm-7"><x-date-time :value="auth()->user()->last_activity_at" /></dd></dl></div></div><div class="col-lg-6"><div class="tf-card p-4"><h2 class="h5">Security Controls</h2><p class="tf-muted">Password changes are managed from your profile page. Two-factor authentication and session controls are reserved for the next platform security release.</p><a href="{{ route('profile.edit') }}" class="btn btn-outline-primary">Open Profile & Password Settings</a></div></div></div>
@endsection
