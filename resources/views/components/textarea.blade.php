@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false, 'rows' => 4])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $error = $errors->first($key);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="ml-0.5 text-rose-500">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except('id')->class(['input', 'input-invalid' => $error]) }}>{{ old($key, $value) }}</textarea>
    @if ($error)
        <p class="field-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
