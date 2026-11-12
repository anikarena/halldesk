<x-layouts.app title="Staff accounts">
    <x-page-header title="Staff accounts" description="People who can sign in to manage the hall. Admins can do everything, staff run daily work.">
        <x-slot:actions>
            <a href="{{ route('manage.staff.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" /> Add staff</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 md:grid-cols-2">
        <div class="card p-5 text-sm">
            <p class="flex items-center gap-2 font-semibold text-slate-800"><x-badge tone="violet">Provost / Admin</x-badge></p>
            <p class="mt-2 text-slate-600">Everything staff can do, plus rooms, seat applications, allocation, monthly bills, notices, reports, staff accounts and settings.</p>
        </div>
        <div class="card p-5 text-sm">
            <p class="flex items-center gap-2 font-semibold text-slate-800"><x-badge tone="blue">Hall Staff</x-badge></p>
            <p class="mt-2 text-slate-600">Meal sheet, payment verification and cash collection, complaints, visitor register, and viewing rooms and students.</p>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Open complaints</th><th>Last sign in</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($members as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$member" size="sm" />
                                    <div><span class="font-medium text-slate-900">{{ $member->name }}</span><span class="block text-xs text-slate-500">{{ $member->email }}</span></div>
                                </div>
                            </td>
                            <td><x-badge :tone="$member->role->tone()">{{ $member->role->label() }}</x-badge></td>
                            <td>{{ $member->phone ?? 'n/a' }}</td>
                            <td class="tabular-nums">{{ $member->assigned_open }}</td>
                            <td class="text-slate-500">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>{!! $member->is_active ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Deactivated</span>' !!}</td>
                            <td class="text-right">
                                @if ($member->id !== auth()->id())
                                    <form method="POST" action="{{ route('manage.staff.toggle', $member) }}">
                                        @csrf
                                        <button type="submit" class="btn {{ $member->is_active ? 'btn-soft-danger' : 'btn-soft' }} btn-sm">{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">You</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
