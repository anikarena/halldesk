@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | ' : '' }}{{ setting('hall_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('partials.app-head')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans text-[15px] leading-[1.7] tracking-[0.01em] text-slate-700 antialiased">
<div class="flex min-h-screen gap-6 p-3 sm:p-5">
    {{-- Form side --}}
    <div class="flex flex-1 flex-col px-2 sm:px-8 lg:px-14">
        <header class="flex items-center justify-between gap-4 py-3">
            <a href="{{ route('home') }}" aria-label="{{ setting('hall_name') }} home"><x-logo /></a>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-brand-600"><x-icon name="arrow-left" class="size-4" /> Home</a>
        </header>

        <div class="flex flex-1 items-center py-10">
            <div @class(['mx-auto w-full', 'max-w-xl' => $wide, 'max-w-md' => ! $wide])>
                @include('partials.flash')
                {{ $slot }}
            </div>
        </div>

        <p class="pb-2 text-xs text-slate-500">{{ setting('hall_address') }}. Hall office: {{ setting('contact_phone') }}. {{ config('hall.disclaimer') }}</p>
    </div>

    {{-- Photo side --}}
    <div class="relative hidden w-[46%] max-w-3xl overflow-hidden rounded-[2rem] bg-cream bg-cover bg-center lg:block"
         style="background-image: url('{{ asset('images/hall/sign-in.jpg') }}')">
        <div class="absolute inset-x-8 bottom-8 rounded-3xl bg-white/90 p-8 backdrop-blur-sm xl:inset-x-10 xl:bottom-10 xl:p-10">
            <h2 class="display text-4xl xl:text-[2.75rem]">Everything About Hall Life, In One Place.</h2>
            <ul class="mt-6 space-y-3 text-sm">
                @foreach ([
                    ['bed', 'Apply for a seat and follow the decision online'],
                    ['utensils', 'Turn meals on or off before the cutoff time'],
                    ['receipt', 'See every bill and pay by bKash, Nagad or Rocket'],
                    ['wrench', 'Report a problem and track it until it is fixed'],
                ] as [$icon, $text])
                    <li class="flex items-center gap-3">
                        <x-icon :name="$icon" stroke="1.5" class="size-5 text-brand-500" />
                        <span class="text-slate-700">{{ $text }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
</body>
</html>
