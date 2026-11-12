<x-layouts.app title="Monthly meals">
    <x-page-header title="Monthly meals" :description="month_label($period).', '.$from->format('j M').' to '.$to->format('j M Y').'. Lunch '.taka(setting('lunch_rate')).', dinner '.taka(setting('dinner_rate')).' per meal.'">
        <x-slot:actions>
            <form method="GET">
                <select name="period" class="input w-44" onchange="this.form.submit()" aria-label="Month">
                    @foreach ($periods as $p)
                        <option value="{{ $p }}" @selected($p === $period)>{{ month_label($p) }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-page-header>

    <section class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Lunches served" :value="number_format($totals['lunch'])" icon="sun" tone="amber" />
        <x-stat label="Dinners served" :value="number_format($totals['dinner'])" icon="moon" tone="violet" />
        <x-stat label="Meal charges" :value="taka($totals['cost'])" icon="receipt" hint="Goes into the monthly bills" />
    </section>

    <div class="card">
        @if ($rows->isEmpty())
            <x-empty icon="utensils" title="Nobody lived in the hall in this month" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Seat</th><th class="text-right">Days in hall</th><th class="text-right">Lunch</th><th class="text-right">Dinner</th><th class="text-right">Meal charge</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><a href="{{ route('manage.students.show', $row['user']) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $row['user']->name }}</a><span class="block text-xs text-slate-500">{{ $row['user']->profile?->student_id }}</span></td>
                                <td class="font-mono text-xs">{{ $row['seat']->code() }}</td>
                                <td class="text-right tabular-nums">{{ $row['days'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['lunch'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['dinner'] }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ taka($row['cost']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 text-sm font-semibold text-slate-900">
                            <td class="px-4 py-3" colspan="3">Total, {{ $rows->count() }} students</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totals['lunch']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totals['dinner']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ taka($totals['cost']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
