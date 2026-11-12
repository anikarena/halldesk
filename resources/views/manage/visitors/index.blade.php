<x-layouts.app title="Visitor register">
    <x-page-header title="Visitor register" :description="$todayCount.' '.str('visitor')->plural($todayCount).' today, '.$inside->count().' inside right now'" />

    <div class="mb-6 grid gap-6 lg:grid-cols-5">
        <form method="POST" action="{{ route('manage.visitors.store') }}" class="card lg:col-span-2">
            @csrf
            <div class="card-header"><h2 class="card-title">Check a visitor in</h2></div>
            <div class="card-body space-y-4">
                <x-select name="user_id" label="Visiting" required placeholder="Choose the resident"
                          :options="$residents->mapWithKeys(fn ($r) => [$r->id => $r->name.'  (seat '.$r->activeAllocation?->seat->code().')'])->all()" />
                <x-input name="visitor_name" label="Visitor name" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-input name="relation" label="Relation" required placeholder="Father, cousin..." list="relations" />
                    <x-input name="phone" label="Phone" placeholder="01712 345678" />
                </div>
                <x-input name="purpose" label="Purpose" placeholder="Optional" />
                <datalist id="relations">
                    @foreach (['Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Cousin', 'Friend', 'Guardian'] as $relation)<option value="{{ $relation }}">@endforeach
                </datalist>
            </div>
            <div class="border-t border-slate-100 px-5 py-4"><button type="submit" class="btn btn-primary w-full"><x-icon name="login" class="size-4" /> Check in now</button></div>
        </form>

        <div class="card lg:col-span-3">
            <div class="card-header">
                <h2 class="card-title">Inside now</h2>
                <x-badge :tone="$inside->isEmpty() ? 'gray' : 'amber'" dot>{{ $inside->count() }}</x-badge>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($inside as $visitor)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <span class="grid size-9 place-items-center rounded-full bg-amber-50 text-amber-700"><x-icon name="user" class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-800">{{ $visitor->visitor_name }} <span class="font-normal text-slate-500">({{ $visitor->relation }})</span></span>
                            <span class="block text-xs text-slate-500">Visiting {{ $visitor->host->name }}, seat {{ $visitor->host->activeAllocation?->seat->code() ?? 'n/a' }}. In since {{ $visitor->check_in_at->format('g:i A') }} ({{ $visitor->check_in_at->diffForHumans(short: true) }})</span>
                        </span>
                        <form method="POST" action="{{ route('manage.visitors.checkout', $visitor) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="logout" class="size-3.5" /> Check out</button>
                        </form>
                    </li>
                @empty
                    <li><x-empty icon="door" title="Nobody inside">Checked in visitors appear here until they leave.</x-empty></li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">History</h2>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="input w-40 py-1.5" aria-label="Date">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input w-56 py-1.5" placeholder="Visitor or resident" aria-label="Search">
                <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="search" class="size-3.5" /></button>
            </form>
        </div>
        @if ($history->isEmpty())
            <x-empty icon="door" title="No visits found" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Visitor</th><th>Phone</th><th>Visited</th><th>In</th><th>Out</th><th>Purpose</th></tr></thead>
                    <tbody>
                        @foreach ($history as $visitor)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $visitor->visitor_name }}<span class="block text-xs font-normal text-slate-500">{{ $visitor->relation }}</span></td>
                                <td>{{ $visitor->phone ?? 'n/a' }}</td>
                                <td>{{ $visitor->host->name }}</td>
                                <td class="whitespace-nowrap">{{ $visitor->check_in_at->format('j M, g:i A') }}</td>
                                <td class="whitespace-nowrap">{{ $visitor->check_out_at->format('g:i A') }}</td>
                                <td class="text-slate-500">{{ $visitor->purpose ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $history->links() }}</div>
        @endif
    </div>
</x-layouts.app>
