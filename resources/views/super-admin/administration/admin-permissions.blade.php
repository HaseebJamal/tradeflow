@extends('layouts.dashboard')
@section('page-title', 'Admin Permissions')
@section('page-subtitle', 'Review platform permission keys assigned to admins')
@section('content')
<div class="row g-4">
@foreach($users as $user)
    <div class="col-lg-6"><div class="tf-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">{{ $user->name }}</h2><div class="tf-muted">{{ $user->email }} · {{ $user->role }}</div></div><span class="badge text-bg-{{ $user->status === 'active' ? 'success' : 'warning' }}">{{ $user->status }}</span></div>
        <div class="d-flex flex-wrap gap-2">@forelse($user->permissions ?? [] as $permission)<span class="badge text-bg-light border">{{ $permission }}</span>@empty<span class="tf-muted">No explicit permissions.</span>@endforelse</div>
    </div></div>
@endforeach
</div>
<div class="tf-card p-4 mt-4"><h2 class="h5">Available Permission Catalog</h2><div class="d-flex flex-wrap gap-2">@foreach($permissions as $permission)<span class="badge text-bg-primary">{{ $permission }}</span>@endforeach</div></div>
@endsection
