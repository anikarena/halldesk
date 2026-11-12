<x-layouts.guest title="Create account" wide>
    <p class="eyebrow">Step 1 of 4</p>
    <h1 class="display mt-3 text-5xl sm:text-6xl">Create Your Account</h1>
    <p class="mt-3 text-slate-500">After signing up you can apply for a seat in {{ setting('hall_name') }}.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-10 space-y-5">
        @csrf
        <x-input name="name" label="Full name" required autocomplete="name" placeholder="As written on your student ID card" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="student_id" label="Student ID" required placeholder="e.g. 221-15-4567" />
            <x-select name="year_of_study" label="Year of study" required :options="[1 => '1st year', 2 => '2nd year', 3 => '3rd year', 4 => '4th year', 5 => '5th year / Masters']" value="1" />
        </div>

        <x-select name="department" label="Department" required placeholder="Choose your department" :options="array_combine($departments, $departments)" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="email" type="email" label="Email address" required autocomplete="email" />
            <x-input name="phone" label="Mobile number" required autocomplete="tel" placeholder="01712 345678" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="password" type="password" label="Password" required autocomplete="new-password" hint="At least 8 characters." />
            <x-input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        </div>

        <button type="submit" class="btn btn-lg btn-primary btn-arrow w-full">
            <span>Create account</span><x-icon name="arrow-right" />
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        Already have an account? <a href="{{ route('login') }}" class="link">Sign in</a>
    </p>
</x-layouts.guest>
