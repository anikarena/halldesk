{{-- Turn push alerts on or off for this phone or browser. With "closable" (dashboard) it is only an invitation. --}}
@php $closable = $closable ?? false; @endphp
<div x-data="pushAlerts(@js($closable))" x-show="state !== 'hidden'" x-cloak class="no-print mb-6 max-w-3xl rounded-2xl bg-cream p-4 ring-1 ring-brand-100">
    <div class="flex items-center gap-4">
        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white text-brand-600 ring-1 ring-brand-100">
            <x-icon name="bell" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="font-medium text-slate-900" x-show="state === 'off'">Get alerts on this device</p>
            <p class="font-medium text-slate-900" x-show="state === 'on'">Alerts are on for this device</p>
            <p class="font-medium text-slate-900" x-show="state === 'blocked'">Alerts are blocked on this device</p>
            <p class="font-medium text-slate-900" x-show="state === 'install'">Get alerts on your iPhone</p>

            <p class="text-sm text-slate-500" x-show="state === 'off'">New bills, payment results, complaint updates and notices, even when HallDesk is closed.</p>
            <p class="text-sm text-slate-500" x-show="state === 'on'">You get every notification on this device, even when HallDesk is closed.</p>
            <p class="text-sm text-slate-500" x-show="state === 'blocked'">Allow notifications for this site in your browser settings, then open this page again.</p>
            <p class="text-sm text-slate-500" x-show="state === 'install'">First add HallDesk to the home screen: in Safari, tap Share, then Add to Home Screen. Then open it from there.</p>
        </div>
        <button type="button" class="btn btn-sm btn-primary shrink-0" x-show="state === 'off'" x-bind:disabled="busy" x-on:click="turnOn()">Turn on</button>
        <button type="button" class="btn btn-sm btn-secondary shrink-0" x-show="state === 'on'" x-bind:disabled="busy" x-on:click="turnOff()">Turn off</button>
        @if ($closable)
            <button type="button" class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-white" aria-label="Close" x-on:click="dismiss()">
                <x-icon name="x" class="size-4" />
            </button>
        @endif
    </div>
    <p class="mt-3 text-sm text-rose-600" x-show="error" x-text="error"></p>
</div>
