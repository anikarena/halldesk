@props(['name', 'label', 'checked' => false, 'hint' => null, 'value' => '1'])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $isChecked = old('_token') ? (bool) old($key) : (bool) $checked;
@endphp
<label for="{{ $id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3.5 py-3 hover:border-slate-300">
    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked($isChecked) {{ $attributes->except('id')->class(['checkbox mt-0.5']) }}>
    <span>
        <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </span>
</label>
