@props(['name', 'id' => null, 'value' => '', 'required' => false, 'readonly' => false, 'disabled' => false, 'error' => null, 'defaultCountry' => 'pk', 'placeholder' => 'Phone number', 'helperText' => null])
@php($inputId = $id ?: 'phone-'.str_replace(['.', '[', ']'], '-', $name))
<div class="tf-phone-input" data-tf-phone-field>
    <input id="{{ $inputId }}" type="tel" class="form-control {{ $error ? 'is-invalid' : '' }}" value="{{ $value }}" autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="{{ $placeholder }}" data-tf-phone-visible data-default-country="{{ $defaultCountry }}" @required($required) @readonly($readonly) @disabled($disabled)>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-tf-phone-value>
    <div class="invalid-feedback {{ $error ? 'd-block' : '' }}" data-tf-phone-feedback>{{ $error }}</div>
    @if($helperText)<small class="tf-muted d-block mt-1">{{ $helperText }}</small>@endif
</div>
