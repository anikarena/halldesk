@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'href' => null])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700',
        'blue' => 'bg-sky-50 text-sky-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'red' => 'bg-rose-50 text-rose-700',
        'violet' => 'bg-violet-50 text-violet-700',
        'gray' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card group flex items-start gap-4 p-5'.($href ? ' transition hover:border-brand-300 hover:shadow-md' : '')]) }}>
    @if ($icon)
        <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0 flex-1">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="mt-2 font-display text-[2.5rem] leading-none text-slate-900">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
        @endif
        {{ $slot }}
    </div>
    @if ($href)
        <x-icon name="chevron-right" class="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
    @endif
</{{ $tag }}>
