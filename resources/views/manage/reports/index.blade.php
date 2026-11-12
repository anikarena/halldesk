<x-layouts.app title="Reports">
    <x-page-header title="Reports" description="Occupancy, money and complaints at a glance. Every chart has a table view.">
        <x-slot:actions>
            <a href="{{ route('manage.reports.dues') }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> Download dues list</a>
            <button type="button" onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="size-4" /> Print</button>
        </x-slot:actions>
    </x-page-header>

    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Occupancy" :value="$occupancy['rate'].'%'" icon="bed" :hint="$occupancy['occupied'].' of '.$occupancy['total'].' seats'" />
        <x-stat label="Outstanding dues" :value="taka($outstanding['amount'])" icon="receipt" tone="amber" :hint="$outstanding['students'].' students'" />
        <x-stat label="Overdue" :value="taka($outstanding['overdue_amount'])" icon="alert" tone="red" :hint="$outstanding['overdue_students'].' students past the due date'" />
        <x-stat label="Average fix time" :value="$complaints['avg_hours'] !== null ? ($complaints['avg_hours'] >= 48 ? round($complaints['avg_hours'] / 24, 1).' days' : $complaints['avg_hours'].' hours') : 'n/a'" icon="wrench" tone="violet"
                :hint="$complaints['resolved_30d'].' complaints resolved in the last 30 days'" />
    </section>

    <div class="card mb-6">
        <div class="card-header"><div><h2 class="card-title">Billed and collected</h2><p class="text-xs text-slate-500">Last 12 months</p></div></div>
        <div class="card-body">@include('partials.finance-chart', ['finance' => $finance, 'height' => 300])</div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Occupancy by floor</h2></div>
            <div class="card-body space-y-5">
                @foreach ($floors as $floor)
                    <x-meter :value="$floor['occupied']" :max="$floor['total']" :label="$floor['label']" :detail="$floor['occupied'].' of '.$floor['total'].' seats'" />
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">By room type</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Type</th><th class="text-right">Rooms</th><th class="text-right">Seats</th><th class="text-right">Occupied</th><th class="text-right">Avg. rent</th></tr></thead>
                    <tbody>
                        @foreach ($roomTypes as $type)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $type['label'] }}</td>
                                <td class="text-right tabular-nums">{{ $type['rooms'] }}</td>
                                <td class="text-right tabular-nums">{{ $type['total'] }}</td>
                                <td class="text-right tabular-nums">{{ $type['occupied'] }} <span class="text-slate-400">({{ $type['total'] ? round($type['occupied'] * 100 / $type['total']) : 0 }}%)</span></td>
                                <td class="text-right tabular-nums">{{ taka($type['rent']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Complaints by category</h2><span class="text-xs text-slate-500">{{ $complaints['open'] }} not resolved</span></div>
            <div class="card-body space-y-4">
                @php $maxComplaints = max(1, $complaints['by_category']->max('total') ?? 1); @endphp
                @forelse ($complaints['by_category'] as $row)
                    <div>
                        <div class="mb-1 flex justify-between text-sm"><span class="font-medium text-slate-700">{{ $row['label'] }}</span><span class="tabular-nums text-slate-500">{{ $row['total'] }} total, {{ $row['open'] }} open</span></div>
                        <div class="h-2 rounded-full bg-brand-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ round($row['total'] * 100 / $maxComplaints) }}%"></div></div>
                    </div>
                @empty
                    <x-empty icon="wrench" title="No complaints yet" />
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Highest dues</h2><a href="{{ route('manage.invoices.index', ['status' => 'open']) }}" class="link text-sm">All unpaid</a></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Seat</th><th class="text-right">Bills</th><th>Oldest due</th><th class="text-right">Due</th></tr></thead>
                    <tbody>
                        @forelse ($topDues as $student)
                            <tr>
                                <td><a href="{{ route('manage.students.show', $student) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $student->name }}</a></td>
                                <td class="font-mono text-xs">{{ $student->activeAllocation?->seat->code() ?? 'Left' }}</td>
                                <td class="text-right tabular-nums">{{ $student->open_bills }}</td>
                                <td @class(['whitespace-nowrap', 'text-rose-600' => \Carbon\Carbon::parse($student->oldest_due)->isPast()])>{{ \Carbon\Carbon::parse($student->oldest_due)->format('j M Y') }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ taka($student->due_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty icon="check-circle" title="Nobody owes anything" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
