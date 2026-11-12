@props(['user', 'size' => 'md'])
@php
    $sizes = ['sm' => 'size-7 text-[11px]', 'md' => 'size-9 text-xs', 'lg' => 'size-12 text-sm', 'xl' => 'size-16 text-lg'];
    // A stable colour per person, so the same student always looks the same
    $tones = ['bg-brand-100 text-brand-800', 'bg-sky-100 text-sky-800', 'bg-amber-100 text-amber-800', 'bg-violet-100 text-violet-800', 'bg-rose-100 text-rose-800', 'bg-emerald-100 text-emerald-800'];
    $tone = $tones[$user->id % count($tones)];
@endphp
<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full font-semibold '.$sizes[$size].' '.$tone]) }} title="{{ $user->name }}">{{ $user->initials() }}</span>
