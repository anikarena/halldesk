{{-- A filled bar with its numbers written beside it, so it never relies on colour alone --}}
@props(['value', 'max', 'label' => null, 'detail' => null, 'tone' => 'brand'])
@php
    $percent = $max > 0 ? min(100, round($value * 100 / $max)) : 0;
    $fills = [
        'brand' => ['bg-brand-600', 'bg-brand-100'],
        'blue' => ['bg-sky-600', 'bg-sky-100'],
        'amber' => ['bg-amber-500', 'bg-amber-100'],
        'red' => ['bg-rose-600', 'bg-rose-100'],
    ];
    [$fill, $track] = $fills[$tone] ?? $fills['brand'];
@endphp
<div {{ $attributes }}>
    @if ($label)
        <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
            <span class="font-medium text-slate-700">{{ $label }}</span>
            <span class="tabular-nums text-slate-500">{{ $detail ?? $value.' of '.$max }} <span class="ml-1 font-semibold text-slate-800">{{ $percent }}%</span></span>
        </div>
    @endif
    <div class="h-2 overflow-hidden rounded-full {{ $track }}" role="meter" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="{{ $max }}">
        <div class="h-full rounded-full {{ $fill }}" style="width: {{ $percent }}%"></div>
    </div>
</div>
