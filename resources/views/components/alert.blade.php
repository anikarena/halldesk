@props(['tone' => 'info', 'title' => null, 'icon' => null])
@php
    $styles = [
        'info' => ['bg-sky-50 border-sky-200 text-sky-900', 'text-sky-600', 'info'],
        'success' => ['bg-emerald-50 border-emerald-200 text-emerald-900', 'text-emerald-600', 'check-circle'],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-900', 'text-amber-600', 'alert'],
        'danger' => ['bg-rose-50 border-rose-200 text-rose-900', 'text-rose-600', 'alert-circle'],
    ];
    [$box, $iconColor, $defaultIcon] = $styles[$tone] ?? $styles['info'];
@endphp
<div {{ $attributes->merge(['class' => "flex gap-3 rounded-xl border px-4 py-3 text-sm {$box}"]) }} role="status">
    <x-icon :name="$icon ?? $defaultIcon" class="mt-0.5 size-5 {{ $iconColor }}" />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-0.5' => $title, 'opacity-90'])>{{ $slot }}</div>
    </div>
    {{ $actions ?? '' }}
</div>
