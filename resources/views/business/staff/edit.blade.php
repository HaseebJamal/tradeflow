@extends('layouts.dashboard')
@section('page-title', 'Edit Staff')
@section('page-subtitle', 'Update employee details, role, status, and permissions')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 mb-0">Edit Staff Account</h2>
        <a href="{{ route('business.staff.show', $staff) }}" class="btn btn-sm btn-outline-secondary">View Profile</a>
    </div>
    <div id="permissions"></div>
    @include('business.staff._form', ['staffMember' => $staff])
</div>
@endsection
