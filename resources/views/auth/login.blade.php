<x-layouts.guest title="Sign in">
    <div x-data="{ email: @js(old('email', '')), password: '' }">
        <p class="eyebrow">Students, staff and the provost office</p>
        <h1 class="display mt-3 text-5xl sm:text-6xl">Welcome Back</h1>
        <p class="mt-3 text-slate-500">Sign in to manage your seat, meals and bills.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-10 space-y-5" x-ref="form">
            @csrf
            <x-input name="email" type="email" label="Email address" required autofocus autocomplete="email" x-model="email" placeholder="you@example.edu" />
            <x-input name="password" type="password" label="Password" required autocomplete="current-password" x-model="password" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    {{-- Inside the installed app, stay signed in unless the student unticks it --}}
                    <input type="checkbox" name="remember" value="1" class="checkbox" x-init="if (matchMedia('(display-mode: standalone)').matches) $el.checked = true"> Keep me signed in
                </label>
                <span class="text-xs text-slate-500">Forgot it? Ask the hall office.</span>
            </div>

            <button type="submit" class="btn btn-lg btn-primary btn-arrow w-full">
                <span>Sign in</span><x-icon name="arrow-right" />
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            New student? <a href="{{ route('register') }}" class="link">Create an account</a> and apply for a seat.
        </p>

        @if (config('hall.demo_logins'))
            <div class="mt-10 rounded-2xl bg-cream p-5">
                <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-600">Demo accounts</p>
                <p class="mt-1 text-xs text-slate-500">Tap one to sign in with it.</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ([
                        ['Provost / Admin', 'provost@hall.test', 'shield'],
                        ['Hall staff', 'staff@hall.test', 'id-card'],
                        ['Resident student', 'student@hall.test', 'bed'],
                        ['New applicant', 'applicant@hall.test', 'clipboard'],
                    ] as [$label, $demoEmail, $icon])
                        <button type="button" class="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5 text-left text-sm ring-1 ring-slate-200 transition hover:ring-brand-400"
                                x-on:click="email = @js($demoEmail); password = @js(config('hall.demo_password')); $nextTick(() => $refs.form.requestSubmit())">
                            <x-icon :name="$icon" stroke="1.5" class="size-5 text-brand-500" />
                            <span class="min-w-0">
                                <span class="block font-medium text-slate-800">{{ $label }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $demoEmail }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.guest>
