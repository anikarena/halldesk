{{-- Public site: info bar, header with menu, page content and footer --}}
@props(['title' => null, 'description' => null])
@php
    $hall = setting('hall_name');
    $links = [
        'Home' => route('home'),
        'Rooms' => route('home').'#rooms',
        'Facilities' => route('home').'#facilities',
        'How to apply' => route('home').'#apply',
        'Notices' => route('home').'#notices',
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | '.$hall : $hall.' | '.setting('university_name') }}</title>
    <meta name="description" content="{{ $description ?? 'Residential hall of '.setting('university_name').'. Apply for a seat, plan meals and pay hall bills online.' }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('partials.app-head')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-[15px] leading-[1.7] tracking-[0.01em] text-slate-700 antialiased"
      x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">

{{-- Info bar --}}
<div class="bg-cream text-[13px] text-slate-600">
    <div class="shell flex flex-wrap items-center justify-between gap-x-6 gap-y-1 py-2">
        <ul class="flex flex-wrap items-center gap-x-6 gap-y-1">
            <li class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-3.5 text-brand-400" /> {{ setting('hall_address') }}</li>
            <li class="hidden items-center gap-1.5 sm:flex"><x-icon name="phone" class="size-3.5 text-brand-400" /> {{ setting('contact_phone') }}</li>
            <li class="hidden items-center gap-1.5 md:flex"><x-icon name="mail" class="size-3.5 text-brand-400" /> {{ setting('contact_email') }}</li>
        </ul>
        <p class="hidden lg:block">{{ setting('university_name') }}</p>
    </div>
</div>

{{-- Header --}}
<header class="shell flex items-center justify-between gap-6 py-5 lg:py-6">
    <div class="flex items-center gap-1">
        {{-- Phones: the menu button sits on the left, like the dashboard --}}
        <button type="button" class="-ml-2 rounded-full p-2 text-slate-800 hover:bg-cream lg:hidden" x-on:click="menu = true" aria-label="Open menu">
            <x-icon name="menu" class="size-7" stroke="1.5" />
        </button>
        <a href="{{ route('home') }}" aria-label="{{ $hall }} home"><x-logo /></a>
    </div>

    <nav class="hidden items-center gap-9 lg:flex" aria-label="Main">
        @foreach ($links as $label => $href)
            <a href="{{ $href }}" @class(['transition hover:text-brand-600', 'text-brand-600' => $loop->first && request()->routeIs('home'), 'text-slate-700' => ! ($loop->first && request()->routeIs('home'))])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="flex items-center gap-2">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-cream hidden rounded-full px-5 py-2.5 sm:inline-flex">Open dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-cream hidden rounded-full px-5 py-2.5 sm:inline-flex">Sign in</a>
            <a href="{{ route('register') }}" class="btn btn-primary hidden rounded-full px-5 py-2.5 md:inline-flex">Apply for a seat</a>
        @endauth
    </div>
</header>

{{-- Mobile menu --}}
<div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="absolute inset-0 bg-slate-900/40" x-show="menu" x-transition.opacity x-on:click="menu = false"></div>
    <div class="absolute inset-y-0 left-0 flex w-full max-w-sm flex-col overflow-y-auto bg-white px-8 py-6 shadow-xl"
         x-show="menu"
         x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
        <div class="flex items-center justify-between">
            <x-logo :subtitle="false" />
            <button type="button" class="rounded-full p-2 text-slate-700 hover:bg-cream" x-on:click="menu = false" aria-label="Close menu">
                <x-icon name="x" class="size-6" stroke="1.5" />
            </button>
        </div>
        <nav class="mt-10 flex flex-col gap-1" aria-label="Main">
            @foreach ($links as $label => $href)
                <a href="{{ $href }}" class="font-display py-2 text-4xl text-slate-900 hover:text-brand-600" x-on:click="menu = false">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="mt-auto grid gap-3 pt-10">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-lg btn-primary">Open dashboard</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-lg btn-primary">Apply for a seat</a>
                <a href="{{ route('login') }}" class="btn btn-lg btn-cream">Sign in</a>
            @endauth
        </div>
    </div>
</div>

<main>
    {{ $slot }}
</main>

{{-- Footer --}}
<footer class="mt-24 border-t border-slate-200 lg:mt-32">
    <div class="shell grid gap-12 py-16 md:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr] lg:py-20">
        <div>
            <x-logo />
            <p class="mt-5 max-w-xs">A residential hall of {{ setting('university_name') }}. A safe seat, nutritious meals and a quiet place to study.</p>
        </div>
        <div>
            <h2 class="display text-3xl">Quick Links</h2>
            <ul class="mt-4 space-y-2">
                @foreach (array_slice($links, 1, null, true) as $label => $href)
                    <li><a href="{{ $href }}" class="hover:text-brand-600">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="display text-3xl">Hall Office</h2>
            <ul class="mt-4 space-y-3">
                <li class="flex gap-2.5"><x-icon name="map-pin" class="mt-1 size-4 text-brand-400" /> {{ setting('hall_address') }}</li>
                <li class="flex gap-2.5"><x-icon name="phone" class="mt-1 size-4 text-brand-400" /> {{ setting('contact_phone') }}</li>
                <li class="flex gap-2.5"><x-icon name="mail" class="mt-1 size-4 text-brand-400" /> <span class="break-all">{{ setting('contact_email') }}</span></li>
            </ul>
        </div>
        <div>
            <h2 class="display text-3xl">Residents</h2>
            <p class="mt-4">Plan meals, pay bills and report problems from your phone.</p>
            <a href="{{ route('login') }}" class="btn btn-lg btn-cream btn-arrow mt-5"><span>Resident sign in</span><x-icon name="arrow-right" /></a>
        </div>
    </div>
    <div class="border-t border-slate-200">
        <div class="shell flex flex-wrap items-center justify-between gap-3 py-6 text-[13px] text-slate-500">
            <p>&copy; {{ now()->year }} {{ $hall }}, {{ setting('university_name') }}.</p>
            <p>{{ config('hall.disclaimer') }}</p>
        </div>
    </div>
</footer>
</body>
</html>
