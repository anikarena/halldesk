{{--
    Billed vs collected per month. Colours: categorical slots 1 and 2, checked
    with the palette validator on a white surface (CVD delta E 24.7, both >= 3:1).
    Every value is also in the table view, the chart never hides a number.
--}}
@php
    $series = [
        ['name' => 'Billed', 'color' => '#2a6fc0', 'data' => $finance->pluck('billed')->all()],
        ['name' => 'Collected', 'color' => '#d16806', 'data' => $finance->pluck('collected')->all()],
    ];
    $chart = ['type' => 'columns', 'labels' => $finance->pluck('label')->all(), 'series' => $series];
@endphp
<div x-data="{ view: 'chart' }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4 text-xs text-slate-600">
            @foreach ($series as $s)
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>
            @endforeach
        </div>
        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium">
            <button type="button" class="rounded-md px-2.5 py-1" x-bind:class="view === 'chart' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'" x-on:click="view = 'chart'">Chart</button>
            <button type="button" class="rounded-md px-2.5 py-1" x-bind:class="view === 'table' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'" x-on:click="view = 'table'">Table</button>
        </div>
    </div>

    <div x-show="view === 'chart'" class="relative" style="height: {{ $height ?? 260 }}px">
        <canvas data-chart='@json($chart)' role="img" aria-label="Billed and collected amount per month"></canvas>
    </div>

    <div x-show="view === 'table'" x-cloak class="table-wrap">
        <table class="table">
            <thead><tr><th>Month</th><th class="text-right">Billed</th><th class="text-right">Collected</th></tr></thead>
            <tbody>
                @foreach ($finance as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right tabular-nums">{{ taka($row['billed']) }}</td>
                        <td class="text-right tabular-nums">{{ taka($row['collected']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">Billed: monthly hall bills made for that month. Collected: payments verified during that month, for any bill.</p>
</div>
