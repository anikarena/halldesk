<x-layouts.app title="Add staff">
    <x-page-header title="Add a staff account" :back="route('manage.staff.index')" back-label="Staff accounts" />

    <form method="POST" action="{{ route('manage.staff.store') }}" class="card max-w-3xl">
        @csrf
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="Full name" required />
            <x-select name="role" label="Role" required value="staff" :options="['staff' => 'Hall Staff', 'admin' => 'Provost / Admin']" />
            <x-input name="email" type="email" label="Email" required />
            <x-input name="phone" label="Mobile number" placeholder="01712 345678" />
            <x-input name="password" type="password" label="Password" required autocomplete="new-password" hint="At least 8 characters." />
            <x-input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
            <a href="{{ route('manage.staff.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create account</button>
        </div>
    </form>
</x-layouts.app>
