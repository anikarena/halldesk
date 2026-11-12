{{-- One "needs attention" item on a dashboard --}}
@props(['tone' => 'amber', 'icon' => 'alert', 'title', 'href' => null, 'action' => 'Open'])
@php
    $styles = [
        'red' => ['border-rose-200 bg-rose-50/70', 'bg-rose-600 text-white', 'text-rose-900', 'text-rose-700'],
        'amber' => ['border-amber-200 bg-amber-50/70', 'bg-amber-500 text-white', 'text-amber-950', 'text-amber-800'],
        'blue' => ['border-sky-200 bg-sky-50/70', 'bg-sky-600 text-white', 'text-sky-950', 'text-sky-800'],
        'brand' => ['border-brand-200 bg-brand-50/70', 'bg-brand-600 text-white', 'text-brand-950', 'text-brand-800'],
    ];
    [$box, $iconBox, $titleColor, $textColor] = $styles[$tone] ?? $styles['amber'];
@endphp
{{-- flex-wrap + a text basis: on a narrow screen the button drops below instead of squeezing the text --}}
<div {{ $attributes->merge(['class' => "flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border p-4 {$box}"]) }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $iconBox }}"><x-icon :name="$icon" class="size-5" /></span>
    <div class="min-w-0 flex-1 basis-52">
        <p class="text-sm font-semibold {{ $titleColor }}">{{ $title }}</p>
        @if ($slot->isNotEmpty())
            <p class="mt-0.5 text-sm {{ $textColor }}">{{ $slot }}</p>
        @endif
    </div>
    @if ($href)
        <a href="{{ $href }}" class="btn btn-secondary btn-sm ml-auto shrink-0">{{ $action }} <x-icon name="arrow-right" class="size-3.5" /></a>
    @endif
</div>
