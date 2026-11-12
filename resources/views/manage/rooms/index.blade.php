<x-layouts.app title="Rooms and seats">
    <x-page-header title="Rooms and seats" :description="$occupancy['occupied'].' of '.$occupancy['total'].' seats taken, '.$occupancy['vacant'].' vacant, '.$occupancy['blocked'].' blocked by maintenance'">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <a href="{{ route('manage.rooms.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add room</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- Filters --}}
    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="w-36">
            <x-select name="floor" label="Floor" placeholder="All floors" :value="$filters['floor'] ?? null"
                      :options="$floors->mapWithKeys(fn ($f) => [$f => \App\Models\Room::floorLabelFor($f)])->all()" />
        </div>
        <div class="w-36">
            <x-select name="capacity" label="Room type" placeholder="All types" :value="$filters['capacity'] ?? null"
                      :options="$capacities->mapWithKeys(fn ($c) => [$c => \App\Models\Room::typeLabelFor($c)])->all()" />
        </div>
        <div class="w-40">
            <x-select name="availability" label="Seats" placeholder="Any" :value="$filters['availability'] ?? null" :options="['vacant' => 'Has a vacant seat', 'full' => 'Full']" />
        </div>
        <div class="w-44">
            <x-select name="status" label="Room status" placeholder="Any" :value="$filters['status'] ?? null" :options="\App\Enums\RoomStatus::options()" />
        </div>
        <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" /> Filter</button>
        @if (array_filter(\Illuminate\Support\Arr::except($filters, 'view'), fn ($v) => $v !== null && $v !== ''))
            <a href="{{ route('manage.rooms.index', ['view' => $view]) }}" class="btn btn-ghost">Clear</a>
        @endif

        <div class="ml-auto inline-flex rounded-lg bg-slate-100 p-0.5 text-sm font-medium">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'map']) }}" @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5', 'bg-white text-slate-900 shadow-xs' => $view === 'map', 'text-slate-500' => $view !== 'map'])><x-icon name="grid" class="size-4" /> Map</a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5', 'bg-white text-slate-900 shadow-xs' => $view === 'list', 'text-slate-500' => $view !== 'list'])><x-icon name="list" class="size-4" /> List</a>
        </div>
    </form>

    @if ($rooms->isEmpty())
        <div class="card"><x-empty icon="grid" title="No rooms match">Change the filters, or add a room.</x-empty></div>
    @elseif ($view === 'map')
        <div class="mb-4 flex flex-wrap items-center gap-4 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="size-3.5 rounded bg-brand-600"></span>Occupied</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-3.5 rounded border border-dashed border-slate-400 bg-white"></span>Vacant</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-3.5 rounded bg-amber-200 ring-1 ring-amber-400"></span>Under maintenance</span>
            @if (auth()->user()->isAdmin())<span class="text-slate-400">Click a vacant seat to allocate it.</span>@endif
        </div>

        <div class="space-y-8">
            @foreach ($byFloor as $floor => $floorRooms)
                @php
                    $seats = $floorRooms->sum('seats_count');
                    $taken = $floorRooms->sum('active_allocations_count');
                @endphp
                <section>
                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <h2 class="text-base font-semibold text-slate-900">{{ \App\Models\Room::floorLabelFor($floor) }}</h2>
                        <x-meter :value="$taken" :max="$seats" class="w-40" />
                        <span class="text-sm text-slate-500">{{ $taken }} of {{ $seats }} seats taken</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                        @foreach ($floorRooms as $room)
                            <div @class([
                                'card p-3.5 transition hover:shadow-md',
                                'bg-amber-50/60 border-amber-200' => ! $room->isAvailable(),
                            ])>
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('manage.rooms.show', $room) }}" class="min-w-0">
                                        <span class="block text-base font-bold text-slate-900 hover:text-brand-700">{{ $room->number }}</span>
                                        <span class="block text-xs text-slate-500">{{ $room->typeLabel() }}</span>
                                    </a>
                                    @if (! $room->isAvailable())
                                        <x-icon name="wrench" class="size-4 text-amber-600" />
                                    @elseif ($room->active_allocations_count >= $room->seats_count)
                                        <x-badge tone="gray">Full</x-badge>
                                    @endif
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach ($room->seats as $seat)
                                        @php $holder = $seat->activeAllocation?->user; @endphp
                                        @if ($holder)
                                            <a href="{{ route('manage.students.show', $holder) }}" class="grid size-8 place-items-center rounded-md bg-brand-600 text-[11px] font-semibold text-white hover:bg-brand-700"
                                               title="Seat {{ $seat->label }}: {{ $holder->name }}">{{ $holder->initials() }}</a>
                                        @elseif (! $room->isAvailable())
                                            <span class="grid size-8 place-items-center rounded-md bg-amber-200 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-400" title="Seat {{ $seat->label }}: blocked">{{ $seat->label }}</span>
                                        @elseif (auth()->user()->isAdmin())
                                            <a href="{{ route('manage.residents.create', ['seat' => $seat->id]) }}" class="grid size-8 place-items-center rounded-md border border-dashed border-slate-400 text-[11px] font-semibold text-slate-500 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700"
                                               title="Seat {{ $seat->label }}: vacant, click to allocate">{{ $seat->label }}</a>
                                        @else
                                            <span class="grid size-8 place-items-center rounded-md border border-dashed border-slate-400 text-[11px] font-semibold text-slate-500" title="Seat {{ $seat->label }}: vacant">{{ $seat->label }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                <p class="mt-3 text-xs text-slate-500">{{ taka($room->monthly_rent) }} per seat</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Room</th><th>Floor</th><th>Type</th><th>Occupancy</th><th class="text-right">Rent per seat</th><th>Facilities</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rooms as $room)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $room->number }}</td>
                                <td>{{ $room->floorLabel() }}</td>
                                <td>{{ $room->typeLabel() }}</td>
                                <td class="w-48"><x-meter :value="$room->active_allocations_count" :max="$room->seats_count" :label="$room->active_allocations_count.' / '.$room->seats_count" detail="" /></td>
                                <td class="text-right tabular-nums">{{ taka($room->monthly_rent) }}</td>
                                <td class="text-slate-500">{{ collect([$room->has_attached_bath ? 'Attached bath' : null, $room->has_balcony ? 'Balcony' : null])->filter()->join(', ') ?: 'Standard' }}</td>
                                <td><x-status :value="$room->status" /></td>
                                <td class="text-right"><a href="{{ route('manage.rooms.show', $room) }}" class="btn btn-ghost btn-sm">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
