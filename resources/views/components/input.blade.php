@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $error = $errors->first($key);
    $current = $type === 'password' ? null : old($key, $value);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="ml-0.5 text-rose-500">*</span>@endif</label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" @if ($current !== null) value="{{ $current }}" @endif
        @required($required) @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('id')->class(['input', 'input-invalid' => $error]) }}>
    @if ($error)
        <p class="field-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
