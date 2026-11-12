<x-layouts.site>

{{-- Hero: hall photo with live seat availability --}}
<section class="shell" data-reveal>
    <div class="relative overflow-hidden rounded-[2rem] bg-cream bg-cover bg-center" style="background-image: url('{{ asset('images/hall/hero.jpg') }}')">
        {{-- Soft light wash on the left so the heading stays readable on any photo --}}
        <div class="absolute inset-0 bg-linear-to-r from-white/75 via-white/35 to-transparent"></div>

        <div class="relative grid items-center gap-10 px-6 py-14 sm:px-10 lg:min-h-[82vh] lg:grid-cols-[1.25fr_1fr] lg:px-16 xl:px-24">
            <div class="max-w-2xl">
                <p class="eyebrow">{{ setting('university_name') }}</p>
                <h1 class="display mt-5 text-[3.2rem] sm:text-7xl xl:text-[5.6rem]">A Quiet Home For Your University Years.</h1>
                <p class="mt-6 max-w-lg text-slate-700">{{ setting('hall_name') }} gives {{ setting('university_name') }} students a furnished seat, nutritious meals and a calm place to study, near the permanent campus.</p>
                <a href="#rooms" class="btn btn-lg btn-arrow mt-8 bg-white text-slate-800 shadow-sm hover:bg-cream"><span>Explore rooms</span><x-icon name="arrow-right" /></a>
            </div>

            <div class="rounded-3xl bg-white p-7 shadow-[0_24px_60px_-20px_rgba(60,35,10,0.25)] sm:p-10 lg:ml-auto lg:w-full lg:max-w-md">
                <h2 class="display text-4xl sm:text-[2.75rem]">Seat Availability</h2>
                <p class="mt-2 text-sm text-slate-500">Live from the hall office, updated the moment a seat changes hands.</p>

                <ul class="mt-6 divide-y divide-slate-200 border-y border-slate-200">
                    @foreach ($roomTypes as $type)
                        <li class="flex items-center justify-between gap-4 py-3.5">
                            <span>
                                <span class="block font-medium text-slate-900">{{ $type['label'] }} room</span>
                                <span class="block text-xs text-slate-500">From {{ taka($type['min_rent']) }} per month</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span @class(['font-display text-4xl leading-none', 'text-brand-600' => $type['vacant'] > 0, 'text-slate-400' => $type['vacant'] === 0])>{{ $type['vacant'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $type['vacant'] === 1 ? 'seat' : 'seats' }} free</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-lg btn-primary btn-arrow mt-7 w-full"><span>Open dashboard</span><x-icon name="arrow-right" /></a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-lg btn-primary btn-arrow mt-7 w-full"><span>Apply for a seat</span><x-icon name="arrow-right" /></a>
                    <p class="mt-4 text-center text-sm text-slate-500">Already a resident? <a href="{{ route('login') }}" class="link">Sign in</a></p>
                @endauth
            </div>
        </div>
    </div>
</section>

{{-- About --}}
<section class="shell py-24 lg:py-32" data-reveal>
    <h2 class="display mx-auto max-w-3xl text-center text-5xl sm:text-6xl xl:text-7xl">{{ setting('hall_name') }}: Your Home On Campus</h2>

    <div class="mt-14 grid items-start gap-6 lg:mt-20 lg:grid-cols-2">
        <div>
            <div class="px-2 py-4 sm:px-10 lg:px-12 lg:py-10">
                <p>Welcome to {{ setting('hall_name') }}, a residential hall of {{ setting('university_name') }} at {{ setting('hall_address') }}. The university has separate halls for male and female students, with well furnished rooms, study areas, common lounges and nutritious dining.</p>
                <p class="mt-4">With HallDesk the hall runs online. Instead of the paper hall request form, students apply for a seat, plan their meals and pay their bills from their phone. Less time in queues, more time for your studies.</p>
                <a href="#apply" class="btn btn-lg btn-cream btn-arrow mt-8"><span>How to apply</span><x-icon name="arrow-right" /></a>
            </div>
            <img src="{{ asset('images/hall/about-1.jpg') }}" alt="A study desk in a hall room" class="mt-4 aspect-[16/10] w-full rounded-2xl object-cover" loading="lazy">
        </div>
        <div class="grid gap-6">
            <img src="{{ asset('images/hall/about-2.jpg') }}" alt="{{ setting('hall_name') }} building" class="aspect-[16/9] w-full rounded-2xl object-cover" loading="lazy">
            <img src="{{ asset('images/hall/about-3.jpg') }}" alt="A clean shared washroom" class="aspect-[4/3] w-full rounded-2xl object-cover sm:w-4/5" loading="lazy">
        </div>
    </div>
</section>

{{-- Numbers --}}
<section class="shell" data-reveal>
    <div class="grid grid-cols-2 gap-y-12 lg:grid-cols-4">
        @foreach ([
            [$occupancy['total'], 'Seats in the hall'],
            [$occupancy['occupied'], 'Students living here'],
            [$rooms, 'Furnished rooms'],
            [$floors, 'Floors'],
        ] as [$number, $label])
            <div class="text-center">
                <p class="relative inline-block font-display text-6xl leading-none text-brand-500 sm:text-7xl">
                    <span class="absolute -right-4 -top-2 -z-10 size-14 rounded-full bg-brand-100/70"></span>{{ $number }}
                </p>
                <p class="mt-4 text-sm text-slate-600">{{ $label }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Rooms --}}
<section id="rooms" class="shell scroll-mt-6 py-24 lg:py-32" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-6">
        <h2 class="display text-5xl sm:text-6xl xl:text-7xl">Explore Our Rooms</h2>
        <a href="{{ route('register') }}" class="btn btn-lg btn-cream btn-arrow"><span>Apply for a seat</span><x-icon name="arrow-right" /></a>
    </div>

    <div class="mt-12 grid gap-x-5 gap-y-10 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($roomTypes as $type)
            <article class="group">
                <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-slate-900">
                    <img src="{{ asset($type['photo']) }}" alt="{{ $type['label'] }} room" loading="lazy"
                         class="size-full object-cover transition duration-500 group-hover:scale-110 group-hover:opacity-45">
                    {{-- Details slide up on hover; the essentials are always shown below the photo too --}}
                    <div class="absolute inset-x-0 bottom-0 translate-y-6 p-7 text-white opacity-0 transition duration-500 group-hover:translate-y-0 group-hover:opacity-100">
                        <h3 class="font-display text-3xl text-white">{{ Str::title($type['label']) }} Room</h3>
                        <table class="mt-3 text-sm">
                            <tr><td class="pr-3 text-white/70">Seats</td><td>{{ $type['capacity'] }} per room</td></tr>
                            <tr><td class="pr-3 text-white/70">Rooms</td><td>{{ $type['rooms'] }} in the hall</td></tr>
                            <tr><td class="pr-3 text-white/70">Rent</td><td>From {{ taka($type['min_rent']) }} a month</td></tr>
                            <tr><td class="pr-3 text-white/70">Includes</td><td>Bed, table, chair, locker</td></tr>
                        </table>
                    </div>
                </div>
                <div class="mt-4 text-center">
                    <h3 class="font-display text-[2rem] leading-tight text-slate-900">{{ Str::title($type['label']) }} Room</h3>
                    <p class="mt-1"><span class="text-xl font-medium text-brand-600">{{ taka($type['min_rent']) }}</span><span class="text-sm text-slate-500"> / month</span></p>
                    <p class="mt-1 text-xs text-slate-500">{{ $type['vacant'] > 0 ? $type['vacant'].' '.($type['vacant'] === 1 ? 'seat' : 'seats').' free now' : 'Full right now' }}</p>
                </div>
            </article>
        @endforeach
    </div>
</section>

{{-- Facilities --}}
<section id="facilities" class="shell scroll-mt-6" data-reveal>
    <h2 class="display mx-auto max-w-2xl text-center text-5xl sm:text-6xl xl:text-7xl">Our Services &amp; Facilities</h2>

    <div class="mt-14 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['utensils', 'Nutritious Dining', 'Lunch and dinner every day. Going home for a few days? Turn your meals off online before 10 PM the day before and pay only for what you eat.'],
            ['book-open', 'Study Areas', 'Quiet study areas in the hall, and the university library with its e-resources on campus.'],
            ['sofa', 'Common Lounges', 'Shared lounges and recreational spaces, a place to relax and meet friends after classes.'],
            ['shield', '24 Hour Security', 'Security day and night, and every visitor is recorded with the time they come and go.'],
            ['wrench', 'Maintenance', 'A maintenance team for the hall. Report a broken fan or a leaking tap online and follow it until it is fixed.'],
            ['users', 'Resident Advisors', 'Resident advisors and hall staff look after the residents and organise activities through the year.'],
        ] as [$icon, $title, $text])
            <div class="rounded-2xl border border-line p-10 text-center transition duration-300 hover:border-brand-500">
                <span class="relative inline-block">
                    <span class="absolute -bottom-1 -left-3 size-9 rounded-full bg-brand-100/80"></span>
                    <x-icon :name="$icon" stroke="1.25" class="relative size-16 text-brand-400" />
                </span>
                <h3 class="display mt-5 text-[2rem]">{{ $title }}</h3>
                <p class="mx-auto mt-3 max-w-sm text-slate-600">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- How to apply --}}
<section id="apply" class="shell scroll-mt-6 py-24 lg:py-32" data-reveal>
    <div class="rounded-[2rem] bg-cream px-6 py-16 sm:px-12 lg:px-20 lg:py-20">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="eyebrow">How to apply</p>
                <h2 class="display mt-3 text-5xl sm:text-6xl">Four Steps To Your Seat</h2>
            </div>
            <a href="{{ route('register') }}" class="btn btn-lg btn-primary btn-arrow"><span>Start your application</span><x-icon name="arrow-right" /></a>
        </div>
        <ol class="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Create An Account', 'Sign up with your '.setting('university_name').' student ID, email address and phone number.'],
                ['Apply Online', 'Tell us your CGPA, home district and why you need a seat.'],
                ['Get A Decision', 'The provost office reviews every application by merit and need, and you are notified.'],
                ['Move In', 'Print your allotment letter, collect your key and settle in.'],
            ] as $i => [$title, $text])
                <li class="border-t border-slate-300 pt-6">
                    <span class="font-display text-6xl leading-none text-brand-300">0{{ $i + 1 }}</span>
                    <h3 class="display mt-4 text-3xl">{{ $title }}</h3>
                    <p class="mt-2 text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- Notices --}}
<section id="notices" class="shell scroll-mt-6" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-6">
        <h2 class="display text-5xl sm:text-6xl xl:text-7xl">Notices &amp; Events</h2>
        @guest
            <a href="{{ route('login') }}" class="btn btn-lg btn-cream btn-arrow"><span>Sign in for all notices</span><x-icon name="arrow-right" /></a>
        @endguest
    </div>

    <div class="mt-12 grid gap-5 md:grid-cols-3">
        @forelse ($notices as $notice)
            <a href="{{ route('public.notice', $notice) }}" class="group flex flex-col rounded-2xl bg-cream p-8 transition hover:bg-sand/70">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-white px-2 py-0.5 text-xs text-slate-700">{{ $notice->category->label() }}</span>
                    @if ($notice->priority->value !== 'normal')<x-status :value="$notice->priority" />@endif
                </span>
                <h3 class="display mt-4 text-3xl group-hover:text-brand-700">{{ $notice->title }}</h3>
                <p class="mt-3 line-clamp-3 text-sm text-slate-600">{{ $notice->body }}</p>
                <p class="mt-auto flex items-center gap-1.5 pt-6 text-sm text-slate-500"><x-icon name="clock" class="size-4" /> {{ $notice->published_at->format('j M, Y') }}</p>
            </a>
        @empty
            <p class="text-slate-500">No public notices right now.</p>
        @endforelse
    </div>
</section>

</x-layouts.site>
