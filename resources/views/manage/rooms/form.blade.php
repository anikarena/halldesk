<x-layouts.app :title="$room->exists ? 'Edit room '.$room->number : 'Add room'">
    <x-page-header :title="$room->exists ? 'Edit room '.$room->number : 'Add a room'"
                   :back="$room->exists ? route('manage.rooms.show', $room) : route('manage.rooms.index')"
                   :description="$room->exists ? 'Changing the capacity adds or removes empty seats at the end.' : 'Seats A, B, C... are created automatically from the capacity.'" />

    <form method="POST" action="{{ $room->exists ? route('manage.rooms.update', $room) : route('manage.rooms.store') }}" class="card max-w-3xl">
        @csrf
        @if ($room->exists) @method('PUT') @endif
        <div class="card-body space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-input name="number" label="Room number" required :value="$room->number" placeholder="e.g. 204" />
                <x-input name="floor" type="number" label="Floor" required min="0" max="30" :value="$room->floor" hint="0 means ground floor." />
                <x-select name="capacity" label="Capacity" required :value="$room->capacity"
                          :options="collect(range(1, \App\Models\Room::MAX_CAPACITY))->mapWithKeys(fn ($c) => [$c => $c.' '.str('seat')->plural($c).' ('.\App\Models\Room::typeLabelFor($c).')'])->all()" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="monthly_rent" type="number" label="Rent per seat per month (৳)" required min="0" :value="$room->monthly_rent" />
                <x-select name="status" label="Status" required :value="$room->status" :options="\App\Enums\RoomStatus::options()"
                          hint="Under maintenance: no new allocation, current residents stay." />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-checkbox name="has_attached_bath" label="Attached bathroom" :checked="$room->has_attached_bath" />
                <x-checkbox name="has_balcony" label="Balcony" :checked="$room->has_balcony" />
            </div>
            <x-input name="notes" label="Notes" :value="$room->notes" placeholder="Anything staff should know, for example window glass broken" />
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-5 py-4">
            @if ($room->exists)
                <button type="submit" form="delete-room" class="btn btn-soft-danger mr-auto"><x-icon name="trash" class="size-4" /> Delete room</button>
            @endif
            <a href="{{ $room->exists ? route('manage.rooms.show', $room) : route('manage.rooms.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $room->exists ? 'Save changes' : 'Add room' }}</button>
        </div>
    </form>

    @if ($room->exists)
        <form id="delete-room" method="POST" action="{{ route('manage.rooms.destroy', $room) }}" onsubmit="return confirm('Delete room {{ $room->number }}? This is only possible if nobody ever lived in it.')">
            @csrf @method('DELETE')
        </form>
    @endif
</x-layouts.app>
