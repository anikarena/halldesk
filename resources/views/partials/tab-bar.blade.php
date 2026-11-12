{{-- Phones only: an app style tab bar at the bottom, Ask AI in the middle. "More" opens the full menu. --}}
@php
    $home = ['Home', route('dashboard'), 'home', request()->routeIs('dashboard'), null];
    $ai = ['Ask AI', route('assistant'), 'sparkle', request()->routeIs('assistant'), null];

    if ($user->isStudent()) {
        $tabs = [
            $home,
            $isResident
                ? ['Meals', route('student.meals'), 'utensils', request()->routeIs('student.meals*'), null]
                : ['Apply', route('student.apply'), 'clipboard', request()->routeIs('student.apply*'), null],
            $ai,
            ['Bills', route('student.bills.index'), 'receipt', request()->routeIs('student.bills*'), null],
        ];
    } else {
        $tabs = [
            $home,
            ['Payments', route('manage.payments.index'), 'wallet', request()->routeIs('manage.payments*'), $navBadges['payments'] ?: null],
            $ai,
            ['Complaints', route('manage.complaints.index'), 'wrench', request()->routeIs('manage.complaints*'), $navBadges['complaints'] ?: null],
        ];
    }
@endphp
<nav class="no-print fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/70 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="Main">
    <div class="grid grid-cols-5">
        @foreach ($tabs as [$label, $href, $icon, $active, $badge])
            <a href="{{ $href }}" @if ($active) aria-current="page" @endif
               @class(['relative flex flex-col items-center gap-1 pb-2 pt-2.5 text-[11px] font-medium', 'text-brand-600' => $active, 'text-slate-500' => ! $active])>
                @if ($icon === 'sparkle')
                    <span @class(['-my-1 grid size-7 place-items-center rounded-full', 'bg-brand-600 text-white' => $active, 'bg-brand-50 text-brand-600' => ! $active])>
                        <x-icon :name="$icon" class="size-4" />
                    </span>
                @else
                    <x-icon :name="$icon" class="size-5" />
                @endif
                {{ $label }}
                @if ($badge)
                    <span class="absolute left-1/2 top-1.5 ml-1.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white ring-2 ring-white">{{ $badge > 9 ? '9+' : $badge }}</span>
                @endif
            </a>
        @endforeach
        <button type="button" class="flex flex-col items-center gap-1 pb-2 pt-2.5 text-[11px] font-medium text-slate-500" x-on:click="sidebar = true">
            <x-icon name="menu" class="size-5" />
            More
        </button>
    </div>
</nav>
