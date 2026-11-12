{{-- Open with: <button type="button" x-data x-on:click="$dispatch('open-modal', 'name')"> --}}
@props(['name', 'title', 'description' => null, 'open' => false])
<div x-data="{ open: @js((bool) $open) }"
     x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
     x-on:close-modal.window="open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50" x-on:click="open = false"></div>
    <div x-show="open"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>
            <button type="button" class="btn btn-ghost -mr-2 p-1.5" x-on:click="open = false" aria-label="Close">
                <x-icon name="x" class="size-5" />
            </button>
        </div>
        <div class="px-6 py-5">{{ $slot }}</div>
    </div>
</div>
