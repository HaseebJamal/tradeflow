@props(['value' => null])
@php
    $date = $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone')) : null;
@endphp
{{ $date ? $date->format('d M, Y h:i A') : '—' }}
