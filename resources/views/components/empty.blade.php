@props(['icon' => 'inbox', 'title'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="grid size-12 place-items-center rounded-full bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-3 text-sm font-semibold text-slate-800">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-1 max-w-sm text-sm text-slate-500">{{ $slot }}</div>
    @endif
</div>
