<x-layouts.app title="Residents">
    <x-page-header title="Residents" :description="$activeCount.' students live in the hall right now'">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <a href="{{ route('manage.residents.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Allocate a seat</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <nav class="tabs mb-4">
        <a href="{{ route('manage.residents.index') }}" @class(['tab', 'tab-active' => $status === 'active'])>Current residents</a>
        <a href="{{ route('manage.residents.index', ['status' => 'ended']) }}" @class(['tab', 'tab-active' => $status === 'ended'])>Past residents</a>
    </nav>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="w-full sm:w-72"><x-input name="q" label="Search" :value="$filters['q'] ?? null" placeholder="Name, student ID, phone" /></div>
        <div class="w-36"><x-select name="floor" label="Floor" placeholder="All floors" :value="$filters['floor'] ?? null" :options="$floors->mapWithKeys(fn ($f) => [$f => \App\Models\Room::floorLabelFor($f)])->all()" /></div>
        <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" /> Search</button>
    </form>

    <div class="card">
        @if ($allocations->isEmpty())
            <x-empty icon="bed" title="Nobody found" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Seat</th><th>Student</th><th>Department</th><th>{{ $status === 'active' ? 'Since' : 'Stayed' }}</th>@if ($status === 'ended')<th>Reason</th>@endif<th></th></tr></thead>
                    <tbody>
                        @foreach ($allocations as $allocation)
                            <tr x-data>
                                <td><span class="inline-flex rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700">{{ $allocation->seat->code() }}</span></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$allocation->user" size="sm" />
                                        <div>
                                            <a href="{{ route('manage.students.show', $allocation->user) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $allocation->user->name }}</a>
                                            <span class="block text-xs text-slate-500">{{ $allocation->user->profile?->student_id }}, {{ $allocation->user->phone }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-w-56 truncate">{{ $allocation->user->profile?->department }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($status === 'active')
                                        {{ $allocation->start_date->format('j M Y') }}
                                    @else
                                        {{ $allocation->start_date->format('j M Y') }} to {{ $allocation->end_date?->format('j M Y') }}
                                    @endif
                                </td>
                                @if ($status === 'ended')<td class="text-slate-500">{{ $allocation->end_reason }}</td>@endif
                                <td class="whitespace-nowrap text-right">
                                    @if ($allocation->isActive() && auth()->user()->isAdmin())
                                        <a href="{{ route('print.allotment', $allocation) }}" class="btn btn-ghost btn-sm" title="Allotment letter"><x-icon name="printer" class="size-3.5" /></a>
                                        <a href="{{ route('manage.residents.transfer', $allocation) }}" class="btn btn-secondary btn-sm"><x-icon name="transfer" class="size-3.5" /> Transfer</a>
                                        <button type="button" class="btn btn-soft-danger btn-sm" x-on:click="$dispatch('open-modal', 'vacate-{{ $allocation->id }}')">Vacate</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $allocations->links() }}</div>
        @endif
    </div>

    {{-- One vacate dialog per row --}}
    @if ($status === 'active' && auth()->user()->isAdmin())
        @foreach ($allocations as $allocation)
            <x-modal :name="'vacate-'.$allocation->id" :title="'Vacate seat '.$allocation->seat->code()" :description="$allocation->user->name.' will leave the hall.'">
                <form method="POST" action="{{ route('manage.residents.vacate', $allocation) }}" class="space-y-4">
                    @csrf
                    <x-input name="last_day" type="date" label="Last day in the hall" required :value="today()->toDateString()" :id="'last_day_'.$allocation->id"
                             hint="Seat rent and meals are billed up to and including this day." />
                    <x-select name="reason" label="Reason" required :id="'reason_'.$allocation->id"
                              :options="['Graduated' => 'Graduated', 'Left the hall' => 'Left the hall', 'Seat cancelled for rule violation' => 'Seat cancelled for rule violation', 'Moved to another hall' => 'Moved to another hall']" />
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn btn-secondary" x-on:click="$dispatch('close-modal')">Cancel</button>
                        <button type="submit" class="btn btn-danger">Vacate seat</button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    @endif
</x-layouts.app>
