@extends('layouts.public')

@section('title', 'Start Free Trial | Profit Point')

@section('content')
    @include('onboarding._register-business-form')
@endsection

@push('scripts')
    <script src="{{ asset('js/register-business.js') }}?v={{ filemtime(public_path('js/register-business.js')) }}"></script>
@endpush
