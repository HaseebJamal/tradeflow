@props(['value' => null])
@php
    $date = $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone')) : null;
@endphp
{{ $date ? $date->format('n/j/Y, g:i A') : '—' }}
