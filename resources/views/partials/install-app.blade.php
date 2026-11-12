{{-- Phones only: offer to install HallDesk as an app. Hidden once installed or closed. --}}
<div x-data="installApp" x-show="show" x-cloak class="no-print mb-6 flex items-center gap-4 rounded-2xl bg-cream p-4 ring-1 ring-brand-100 lg:hidden">
    <img src="{{ asset('icons/icon-192.png') }}" alt="" class="size-12 shrink-0 rounded-xl">
    <div class="min-w-0 flex-1">
        <p class="font-medium text-slate-900">Get the HallDesk app</p>
        <p class="text-sm text-slate-500" x-show="!ios">Open your hall in one tap from the home screen.</p>
        <p class="text-sm text-slate-500" x-show="ios">In Safari, tap Share, then Add to Home Screen.</p>
    </div>
    <button type="button" class="btn btn-sm btn-primary shrink-0" x-show="!ios" x-on:click="install()">Install</button>
    <button type="button" class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-white" aria-label="Close" x-on:click="dismiss()">
        <x-icon name="x" class="size-4" />
    </button>
</div>
