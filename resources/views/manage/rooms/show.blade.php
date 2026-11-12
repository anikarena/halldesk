<x-layouts.app :title="'Room '.$room->number">
    <x-page-header :title="'Room '.$room->number" :back="route('manage.rooms.index')" back-label="Rooms"
                   :description="$room->floorLabel().', '.$room->typeLabel().' room, '.taka($room->monthly_rent).' per seat per month'">
        <x-slot:actions>
            <x-status :value="$room->status" />
            @if (auth()->user()->isAdmin())
                <a href="{{ route('manage.rooms.edit', $room) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4" /> Edit room</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (! $room->isAvailable())
        <x-alert tone="warning" class="mb-6" title="Under maintenance">No new student can be placed here until the room is set back to available.@if ($room->notes) Note: {{ $room->notes }}@endif</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Seats</h2></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($room->seats as $seat)
                    @php $allocation = $seat->activeAllocation; @endphp
                    <li class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <span class="grid size-11 place-items-center rounded-lg bg-slate-100 text-base font-bold text-slate-700">{{ $seat->label }}</span>
                        @if ($allocation)
                            <x-avatar :user="$allocation->user" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('manage.students.show', $allocation->user) }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-brand-700">{{ $allocation->user->name }}</a>
                                <p class="truncate text-xs text-slate-500">{{ $allocation->user->profile?->student_id }}, {{ $allocation->user->profile?->department }}. Since {{ $allocation->start_date->format('j M Y') }}</p>
                            </div>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('manage.residents.transfer', $allocation) }}" class="btn btn-secondary btn-sm"><x-icon name="transfer" class="size-3.5" /> Transfer</a>
                            @endif
                        @else
                            <div class="flex-1 text-sm text-slate-500">Vacant</div>
                            @if (auth()->user()->isAdmin() && $room->isAvailable())
                                <a href="{{ route('manage.residents.create', ['seat' => $seat->id]) }}" class="btn btn-soft btn-sm"><x-icon name="plus" class="size-3.5" /> Allocate</a>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <dl class="divide-y divide-slate-100 px-5">
                    <div class="dl-row"><dt>Capacity</dt><dd>{{ $room->capacity }} {{ str('seat')->plural($room->capacity) }}</dd></div>
                    <div class="dl-row"><dt>Rent per seat</dt><dd>{{ taka($room->monthly_rent) }}</dd></div>
                    <div class="dl-row"><dt>Attached bath</dt><dd>{{ $room->has_attached_bath ? 'Yes' : 'No' }}</dd></div>
                    <div class="dl-row"><dt>Balcony</dt><dd>{{ $room->has_balcony ? 'Yes' : 'No' }}</dd></div>
                    @if ($room->notes)<div class="dl-row"><dt>Notes</dt><dd>{{ $room->notes }}</dd></div>@endif
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Recent complaints</h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($complaints as $complaint)
                        <li>
                            <a href="{{ route('manage.complaints.show', $complaint) }}" class="flex items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-800">{{ $complaint->title }}</span>
                                    <span class="block text-xs text-slate-500">{{ $complaint->created_at->format('j M Y') }}</span>
                                </span>
                                <x-status :value="$complaint->status" />
                            </a>
                        </li>
                    @empty
                        <li><x-empty icon="check-circle" title="No complaints for this room" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @if ($history->isNotEmpty())
        <div class="card mt-6">
            <div class="card-header"><h2 class="card-title">Past residents</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Seat</th><th>From</th><th>To</th><th>Reason</th></tr></thead>
                    <tbody>
                        @foreach ($history as $past)
                            <tr>
                                <td><a href="{{ route('manage.students.show', $past->user) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $past->user->name }}</a></td>
                                <td>{{ $past->seat->label }}</td>
                                <td>{{ $past->start_date->format('j M Y') }}</td>
                                <td>{{ $past->end_date?->format('j M Y') }}</td>
                                <td class="text-slate-500">{{ $past->end_reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
