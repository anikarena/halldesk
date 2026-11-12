@props(['title' => null, 'description' => null, 'back' => null, 'backLabel' => 'Back'])
<div {{ $attributes->merge(['class' => 'mb-8']) }}>
    @if ($back)
        <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-brand-600">
            <x-icon name="arrow-left" class="size-4" /> {{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="display text-4xl sm:text-[2.75rem]">{{ $title }}</h1>
            @if ($description)
                <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
