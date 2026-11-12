@props(['title' => null])
@php
    $user = auth()->user();
    $hall = setting('hall_name');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Push alerts (resources/js/app.js): the server's public key and whose device this is now --}}
    @if ($pushKey = app(\App\Services\Push\WebPush::class)->publicKey())
        <meta name="push-key" content="{{ $pushKey }}">
        <meta name="user-id" content="{{ $user->id }}">
    @endif
    <title>{{ $title ? $title.' | ' : '' }}{{ $hall }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('partials.app-head')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased" x-data="{ sidebar: false }" x-on:keydown.escape.window="sidebar = false">

{{-- Mobile backdrop --}}
<div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" x-on:click="sidebar = false"></div>

{{-- Sidebar --}}
<aside class="no-print fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200/70 bg-cream transition-transform duration-200 lg:translate-x-0"
       x-bind:class="sidebar && 'translate-x-0!'">
    <div class="flex h-20 shrink-0 items-center justify-between px-5">
        <a href="{{ route('dashboard') }}"><x-logo /></a>
        <button type="button" class="rounded-md p-1 text-slate-500 hover:bg-white lg:hidden" x-on:click="sidebar = false" aria-label="Close menu">
            <x-icon name="x" />
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-5 pt-2">
        <div class="space-y-1">
            <x-nav-link :href="route('dashboard')" icon="dashboard" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
        </div>

        @if ($user->isStudent())
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">My hall</p>
                @if ($isResident)
                    <x-nav-link :href="route('student.room')" icon="bed" :active="request()->routeIs('student.room')">My room</x-nav-link>
                    <x-nav-link :href="route('student.meals')" icon="utensils" :active="request()->routeIs('student.meals*')">Meals</x-nav-link>
                @else
                    <x-nav-link :href="route('student.apply')" icon="clipboard" :active="request()->routeIs('student.apply*')">Apply for a seat</x-nav-link>
                @endif
                <x-nav-link :href="route('student.bills.index')" icon="receipt" :active="request()->routeIs('student.bills*')">Bills and payments</x-nav-link>
                <x-nav-link :href="route('student.complaints.index')" icon="wrench" :active="request()->routeIs('student.complaints*')">Complaints</x-nav-link>
                <x-nav-link :href="route('student.visitors')" icon="door" :active="request()->routeIs('student.visitors')">My visitors</x-nav-link>
            </div>
        @else
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">Residence</p>
                <x-nav-link :href="route('manage.rooms.index')" icon="grid" :active="request()->routeIs('manage.rooms*')">Rooms and seats</x-nav-link>
                <x-nav-link :href="route('manage.residents.index')" icon="bed" :active="request()->routeIs('manage.residents*')">Residents</x-nav-link>
                @if ($user->isAdmin())
                    <x-nav-link :href="route('manage.applications.index')" icon="clipboard" :active="request()->routeIs('manage.applications*')" :badge="$navBadges['applications'] ?: null">Seat applications</x-nav-link>
                @endif
                <x-nav-link :href="route('manage.students.index')" icon="id-card" :active="request()->routeIs('manage.students*')">Students</x-nav-link>
            </div>
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">Dining</p>
                <x-nav-link :href="route('manage.meals.index')" icon="utensils" :active="request()->routeIs('manage.meals.index')">Daily meal sheet</x-nav-link>
                <x-nav-link :href="route('manage.meals.monthly')" icon="calendar" :active="request()->routeIs('manage.meals.monthly')">Monthly meals</x-nav-link>
            </div>
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">Finance</p>
                <x-nav-link :href="route('manage.invoices.index')" icon="receipt" :active="request()->routeIs('manage.invoices.index', 'manage.invoices.show', 'manage.invoices.create')">Bills</x-nav-link>
                <x-nav-link :href="route('manage.payments.index')" icon="wallet" :active="request()->routeIs('manage.payments*')" :badge="$navBadges['payments'] ?: null">Payment checks</x-nav-link>
                @if ($user->isAdmin())
                    <x-nav-link :href="route('manage.invoices.generate')" icon="sparkle" :active="request()->routeIs('manage.invoices.generate*')">Generate monthly bills</x-nav-link>
                @endif
            </div>
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">Support</p>
                <x-nav-link :href="route('manage.complaints.index')" icon="wrench" :active="request()->routeIs('manage.complaints*')" :badge="$navBadges['complaints'] ?: null">Complaints</x-nav-link>
                <x-nav-link :href="route('manage.visitors.index')" icon="door" :active="request()->routeIs('manage.visitors*')">Visitor register</x-nav-link>
            </div>
            @if ($user->isAdmin())
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">Office</p>
                    <x-nav-link :href="route('manage.notices.index')" icon="megaphone" :active="request()->routeIs('manage.notices*')">Manage notices</x-nav-link>
                    <x-nav-link :href="route('manage.reports.index')" icon="chart" :active="request()->routeIs('manage.reports*')">Reports</x-nav-link>
                    <x-nav-link :href="route('manage.staff.index')" icon="shield" :active="request()->routeIs('manage.staff*')">Staff accounts</x-nav-link>
                    <x-nav-link :href="route('manage.settings.edit')" icon="settings" :active="request()->routeIs('manage.settings*')">Hall settings</x-nav-link>
                </div>
            @endif
        @endif

        <div class="space-y-1">
            <p class="px-3 pb-1 text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400">Information</p>
            <x-nav-link :href="route('notices.index')" icon="megaphone" :active="request()->routeIs('notices.*')">Notice board</x-nav-link>
            <x-nav-link :href="route('rules')" icon="book-open" :active="request()->routeIs('rules')">Hall rules</x-nav-link>
            <x-nav-link :href="route('notifications.index')" icon="bell" :active="request()->routeIs('notifications.*')" :badge="$unreadCount ?: null">Notifications</x-nav-link>
        </div>
    </nav>

    <div class="border-t border-slate-200/70 p-3">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-white">
            <x-avatar :user="$user" size="sm" />
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-slate-900">{{ $user->name }}</span>
                <span class="block truncate text-xs text-slate-500">{{ $user->role->label() }}</span>
            </span>
        </a>
    </div>
</aside>

<div class="flex min-h-full flex-col pb-[calc(4.25rem+env(safe-area-inset-bottom))] lg:pb-0 lg:pl-64 print:p-0">
    {{-- Top bar --}}
    <header class="no-print sticky top-0 z-30 flex h-20 shrink-0 items-center gap-3 border-b border-slate-200/70 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-10">
        <button type="button" class="-ml-1 rounded-md p-1.5 text-slate-600 hover:bg-slate-100 lg:hidden" x-on:click="sidebar = true" aria-label="Open menu">
            <x-icon name="menu" />
        </button>
        <div class="min-w-0 flex-1">
            <p class="hidden truncate text-sm text-slate-500 sm:block">
                <span class="font-medium text-slate-700">{{ now()->format('l, j F Y') }}</span>
                <span class="mx-1.5 text-slate-300">|</span>{{ setting('university_name') }}
            </p>
        </div>

        {{-- Phones have Ask AI in the tab bar, larger screens get it here --}}
        <a href="{{ route('assistant') }}" @class([
            'hidden items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium transition sm:inline-flex',
            'bg-brand-600 text-white' => request()->routeIs('assistant'),
            'bg-brand-50 text-brand-700 ring-1 ring-brand-100 hover:bg-brand-100' => ! request()->routeIs('assistant'),
        ])>
            <x-icon name="sparkle" class="size-4" /> Ask AI
        </a>

        <a href="{{ route('notifications.index') }}" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Notifications">
            <x-icon name="bell" />
            @if ($unreadCount)
                <span class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white ring-2 ring-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
            @endif
        </a>

        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <button type="button" class="flex items-center gap-2 rounded-full p-0.5 pr-2 hover:bg-slate-100" x-on:click="open = !open" x-bind:aria-expanded="open">
                <x-avatar :user="$user" size="sm" />
                <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ $user->firstName() }}</span>
                <x-icon name="chevron-down" class="size-4 text-slate-400" />
            </button>
            <div x-show="open" x-cloak x-transition.origin.top.right
                 class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                </div>
                <div class="p-1.5">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <x-icon name="user" class="size-4 text-slate-400" /> My profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                            <x-icon name="logout" class="size-4" /> Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- An urgent notice stays on top of every page until it expires or is closed --}}
    @if ($urgentNotice)
        <div x-data="{
                 hidden: false,
                 key: 'notice-closed-{{ $urgentNotice->id }}',
                 init() { try { this.hidden = localStorage.getItem(this.key) === '1' } catch (e) {} },
                 close() { this.hidden = true; try { localStorage.setItem(this.key, '1') } catch (e) {} },
             }"
             x-show="!hidden" x-cloak
             class="no-print border-b border-rose-200 bg-rose-50">
            <div class="flex items-center gap-3 px-4 py-2.5 sm:px-6 lg:px-10">
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-rose-600 text-white">
                    <x-icon name="megaphone" class="size-4" />
                </span>
                <p class="min-w-0 flex-1 text-sm text-rose-900">
                    <span class="mr-1 font-bold uppercase tracking-wide">Urgent:</span>
                    <a href="{{ route('notices.show', $urgentNotice) }}" class="font-medium underline-offset-2 hover:underline">{{ $urgentNotice->title }}</a>
                </p>
                <a href="{{ route('notices.show', $urgentNotice) }}" class="hidden text-sm font-semibold text-rose-700 hover:text-rose-900 sm:block">Read notice</a>
                <button type="button" class="rounded-md p-1 text-rose-500 hover:bg-rose-100" aria-label="Close" x-on:click="close()">
                    <x-icon name="x" class="size-4" />
                </button>
            </div>
        </div>
    @endif

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
        @include('partials.flash')
        @if (request()->routeIs('dashboard'))
            @include('partials.install-app')
            @include('partials.push-alerts', ['closable' => true])
        @endif
        {{ $slot }}
    </main>

    <footer class="no-print mx-auto w-full max-w-7xl px-4 pb-6 text-xs text-slate-400 sm:px-6 lg:px-10">
        {{ $hall }}, {{ setting('university_name') }}. Hall office: {{ setting('contact_phone') }}. {{ config('hall.disclaimer') }}
    </footer>
</div>

@include('partials.tab-bar')
</body>
</html>
