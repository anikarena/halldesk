@props(['dark' => false, 'subtitle' => true])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    {{-- An arched doorway: the hall, and a way in --}}
    <svg class="h-10 w-9 shrink-0" viewBox="0 0 36 40" aria-hidden="true">
        <path d="M2 40V17a16 16 0 0 1 32 0v23z" fill="#f4cfa9"/>
        <path d="M11 40V24.5a7 7 0 0 1 14 0V40" fill="none" stroke="#d16806" stroke-width="2.6"/>
        <circle cx="18" cy="12" r="2.2" fill="#d16806"/>
    </svg>
    <span class="leading-none">
        <span @class(['block font-display text-[23px] font-medium uppercase tracking-[0.2em]', 'text-white' => $dark, 'text-slate-900' => ! $dark])>HallDesk</span>
        @if ($subtitle)
            <span @class(['mt-1 block max-w-44 truncate text-[10px] font-medium uppercase tracking-[0.14em]', 'text-white/70' => $dark, 'text-slate-500' => ! $dark])>{{ setting('hall_name') }}</span>
        @endif
    </span>
</span>
