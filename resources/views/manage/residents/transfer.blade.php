<x-layouts.app title="Transfer resident">
    <x-page-header :title="'Transfer '.$allocation->user->name" :back="route('manage.residents.index')" back-label="Residents"
                   :description="'Now on seat '.$allocation->seat->code().' since '.$allocation->start_date->format('j M Y')" />

    <form method="POST" action="{{ route('manage.residents.transfer.store', $allocation) }}" class="card max-w-3xl">
        @csrf
        <div class="card-body space-y-5">
            <div class="flex items-center gap-4 rounded-lg bg-slate-50 p-4">
                <span class="rounded-md bg-white px-3 py-2 font-mono text-sm font-bold text-slate-800 ring-1 ring-slate-200">{{ $allocation->seat->code() }}</span>
                <x-icon name="arrow-right" class="size-5 text-slate-400" />
                <span class="text-sm text-slate-500">New seat below. The old stay ends the day before the transfer date, so the bill is split correctly.</span>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="seat_id" label="New seat" required placeholder="Choose a vacant seat"
                          :options="$seats->mapWithKeys(fn ($s) => [$s->id => $s->code().'  ('.$s->room->typeLabel().', '.$s->room->floorLabel().', '.taka($s->room->monthly_rent).')'])->all()" />
                <x-input name="date" type="date" label="Transfer date" required :value="today()->toDateString()" />
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
            <a href="{{ route('manage.residents.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><x-icon name="transfer" class="size-4" /> Transfer</button>
        </div>
    </form>
</x-layouts.app>
