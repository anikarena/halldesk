<x-layouts.app title="Daily meal sheet">
    <x-page-header title="Daily meal sheet" :description="$date->format('l, j F Y')">
        <x-slot:actions>
            <a href="{{ route('manage.meals.index', ['date' => $date->subDay()->toDateString()]) }}" class="btn btn-secondary" aria-label="Previous day"><x-icon name="chevron-left" class="size-4" /></a>
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date->toDateString() }}" class="input w-40" onchange="this.form.submit()" aria-label="Choose a date">
            </form>
            <a href="{{ route('manage.meals.index', ['date' => $date->addDay()->toDateString()]) }}" class="btn btn-secondary" aria-label="Next day"><x-icon name="chevron-right" class="size-4" /></a>
            <button type="button" onclick="window.print()" class="btn btn-primary"><x-icon name="printer" class="size-4" /> Print for kitchen</button>
        </x-slot:actions>
    </x-page-header>

    @if ($locked)
        <x-alert tone="success" icon="lock" class="mb-6" title="Final numbers">Students can no longer change meals for this day, so these numbers are final for cooking.</x-alert>
    @else
        <x-alert tone="warning" icon="clock" class="mb-6" title="Numbers can still change">Students can change meals for this day until {{ $cutoff->format('g:i A, j M') }}.</x-alert>
    @endif

    <section class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card p-6">
            <p class="flex items-center gap-2 text-sm font-medium text-slate-500"><x-icon name="sun" class="size-4 text-amber-500" /> Lunch to cook</p>
            <p class="mt-2 text-5xl font-semibold tracking-tight text-slate-900">{{ $count['lunch'] }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $lunchOff->count() }} turned off</p>
        </div>
        <div class="card p-6">
            <p class="flex items-center gap-2 text-sm font-medium text-slate-500"><x-icon name="moon" class="size-4 text-violet-500" /> Dinner to cook</p>
            <p class="mt-2 text-5xl font-semibold tracking-tight text-slate-900">{{ $count['dinner'] }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $dinnerOff->count() }} turned off</p>
        </div>
        <div class="card p-6">
            <p class="flex items-center gap-2 text-sm font-medium text-slate-500"><x-icon name="users" class="size-4 text-brand-600" /> Residents that day</p>
            <p class="mt-2 text-5xl font-semibold tracking-tight text-slate-900">{{ $count['residents'] }}</p>
            <p class="mt-1 text-sm text-slate-500">Everyone eats unless they turned the meal off</p>
        </div>
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        @foreach (['Lunch' => $lunchOff, 'Dinner' => $dinnerOff] as $label => $offs)
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ $label }} off ({{ $offs->count() }})</h2></div>
                @if ($offs->isEmpty())
                    <x-empty icon="check-circle" title="Everybody eats" />
                @else
                    <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                        @foreach ($offs->sortBy(fn ($o) => $o->user->activeAllocation?->seat->code()) as $off)
                            <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                                <span class="w-16 font-mono text-xs font-semibold text-slate-600">{{ $off->user->activeAllocation?->seat->code() ?? 'Left' }}</span>
                                <span class="flex-1 text-slate-800">{{ $off->user->name }}</span>
                                <span class="text-xs text-slate-400">{{ $off->user->profile?->student_id }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    <div class="card no-print">
        <div class="card-header"><h2 class="card-title">Next 7 days</h2><span class="text-xs text-slate-500">Plan the market. Later days may still change.</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th class="text-right">Residents</th><th class="text-right">Lunch</th><th class="text-right">Dinner</th></tr></thead>
                <tbody>
                    @foreach ($week as $row)
                        <tr>
                            <td><a href="{{ route('manage.meals.index', ['date' => $row['date']->toDateString()]) }}" class="font-medium text-slate-800 hover:text-brand-700">{{ $row['date']->format('D, j M') }}</a> @if ($row['date']->isToday())<x-badge tone="brand" class="ml-1">Today</x-badge>@endif</td>
                            <td class="text-right tabular-nums">{{ $row['count']['residents'] }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $row['count']['lunch'] }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $row['count']['dinner'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
