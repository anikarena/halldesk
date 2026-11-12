<x-layouts.app title="Allocate a seat">
    <x-page-header title="Allocate a seat" :back="route('manage.residents.index')" back-label="Residents"
                   description="Give a vacant seat to a student directly. A pending online application of the student is approved at the same time." />

    <form method="POST" action="{{ route('manage.residents.store') }}" class="card max-w-3xl">
        @csrf
        <div class="card-body space-y-5">
            @if ($students->isEmpty())
                <x-alert tone="info">Every active student already has a seat. <a href="{{ route('manage.students.create') }}" class="link">Add a student</a> first.</x-alert>
            @endif
            <x-select name="user_id" label="Student" required placeholder="Choose a student without a seat" :value="$selectedStudent"
                      :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->name.'  ('.($s->profile?->student_id ?? 'no ID').', '.($s->profile?->department ?? '').')'])->all()"
                      hint="Only active students who do not have a seat are listed." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="seat_id" label="Seat" required placeholder="Choose a vacant seat" :value="$selectedSeat"
                          :options="$seats->mapWithKeys(fn ($s) => [$s->id => $s->code().'  ('.$s->room->typeLabel().', '.$s->room->floorLabel().')'])->all()" />
                <x-input name="start_date" type="date" label="Start date" required :value="today()->toDateString()" />
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
            <a href="{{ route('manage.residents.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" @disabled($students->isEmpty() || $seats->isEmpty())><x-icon name="check" class="size-4" /> Allocate seat</button>
        </div>
    </form>
</x-layouts.app>
