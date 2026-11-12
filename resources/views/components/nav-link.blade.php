@props(['href', 'icon', 'active' => false, 'badge' => null])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   @class([
       'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition',
       'bg-white font-medium text-slate-900 shadow-[0_1px_3px_rgba(60,40,20,0.08)]' => $active,
       'text-slate-600 hover:bg-white/70 hover:text-slate-900' => ! $active,
   ])>
    <x-icon :name="$icon" stroke="1.75" class="size-[18px] {{ $active ? 'text-brand-500' : 'text-slate-400 group-hover:text-brand-500' }}" />
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="min-w-5 rounded-full bg-brand-500 px-1.5 py-px text-center text-[11px] font-semibold text-white">{{ $badge }}</span>
    @endif
</a>
