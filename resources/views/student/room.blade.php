<x-layouts.app title="My room">
    <x-page-header :title="'Room '.$room->number" :description="$room->floorLabel().', '.$room->typeLabel().' room, seat '.$allocation->seat->label.' is yours'">
        <x-slot:actions>
            <a href="{{ route('print.allotment', $allocation) }}" class="btn btn-secondary"><x-icon name="printer" class="size-4" /> Allotment letter</a>
            <a href="{{ route('student.complaints.create') }}" class="btn btn-primary"><x-icon name="wrench" class="size-4" /> Report a problem</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Who is in the room</h2></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                @foreach ($room->seats as $seat)
                    @php $occupant = $roomAllocations->get($seat->id); @endphp
                    <div @class([
                        'flex items-center gap-3 rounded-xl border p-4',
                        'border-brand-300 bg-brand-50 ring-1 ring-brand-200' => $seat->id === $allocation->seat_id,
                        'border-slate-200' => $seat->id !== $allocation->seat_id && $occupant,
                        'border-dashed border-slate-300 bg-slate-50' => ! $occupant,
                    ])>
                        <span class="grid size-11 place-items-center rounded-lg bg-white text-base font-bold text-slate-700 ring-1 ring-slate-200">{{ $seat->label }}</span>
                        @if ($occupant)
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-slate-800">{{ $occupant->user->name }} @if ($seat->id === $allocation->seat_id)<span class="font-normal text-brand-700">(you)</span>@endif</span>
                                <span class="block truncate text-xs text-slate-500">{{ $occupant->user->profile?->department }}, {{ $occupant->user->profile?->yearLabel() }}</span>
                            </span>
                        @else
                            <span class="text-sm text-slate-500">Empty seat</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Room details</h2></div>
            <dl class="divide-y divide-slate-100 px-5">
                <div class="dl-row"><dt>Seat</dt><dd>{{ $allocation->seat->code() }}</dd></div>
                <div class="dl-row"><dt>Since</dt><dd>{{ $allocation->start_date->format('j M Y') }}</dd></div>
                <div class="dl-row"><dt>Hall rent</dt><dd>{{ taka($room->monthly_rent) }}</dd></div>
                <div class="dl-row"><dt>Electricity</dt><dd>{{ taka((int) setting('electricity_charge')) }}</dd></div>
                <div class="dl-row"><dt class="font-medium text-slate-900">Total per month</dt><dd class="text-brand-700">{{ taka($room->monthly_rent + (int) setting('electricity_charge')) }}</dd></div>
                <div class="dl-row"><dt>Attached bath</dt><dd>{{ $room->has_attached_bath ? 'Yes' : 'No' }}</dd></div>
                <div class="dl-row"><dt>Balcony</dt><dd>{{ $room->has_balcony ? 'Yes' : 'No' }}</dd></div>
                @if ($allocation->allocatedBy)
                    <div class="dl-row"><dt>Allocated by</dt><dd>{{ $allocation->allocatedBy->name }}</dd></div>
                @endif
            </dl>
        </div>
    </div>

    @if ($history->count() > 1)
        <div class="card mt-6">
            <div class="card-header"><h2 class="card-title">Seat history</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Seat</th><th>From</th><th>To</th><th>Status</th><th>Note</th></tr></thead>
                    <tbody>
                        @foreach ($history as $item)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $item->seat->code() }}</td>
                                <td>{{ $item->start_date->format('j M Y') }}</td>
                                <td>{{ $item->end_date?->format('j M Y') ?? 'Now' }}</td>
                                <td><x-status :value="$item->status" /></td>
                                <td class="text-slate-500">{{ $item->end_reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
