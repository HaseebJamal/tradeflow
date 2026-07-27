@props(['value' => 0, 'precision' => 3])

@php
    $formatted = number_format((float) $value, (int) $precision, '.', ',');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
@endphp

{{ $formatted === '-0' || $formatted === '' ? '0' : $formatted }}
