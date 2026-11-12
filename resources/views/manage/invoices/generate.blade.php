<x-layouts.app title="Generate monthly bills">
    <x-page-header title="Generate monthly bills" :back="route('manage.invoices.index')" back-label="Bills"
                   description="Step 1: check the preview. Step 2: create the bills. Students who already have a bill for the month are skipped." />

    <div class="card mb-6 p-5">
        <div class="flex flex-wrap items-end gap-4">
            <form method="GET" class="w-56">
                <label for="period" class="label">Billing month</label>
                <select id="period" name="period" class="input" onchange="this.form.submit()">
                    @foreach ($periods as $p)
                        <option value="{{ $p }}" @selected($p === $period)>{{ month_label($p) }}</option>
                    @endforeach
                </select>
            </form>
            <div class="grid flex-1 grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div><p class="text-slate-500">New bills</p><p class="text-xl font-semibold text-slate-900">{{ $newCount }}</p></div>
                <div><p class="text-slate-500">New total</p><p class="text-xl font-semibold text-slate-900">{{ taka($newTotal) }}</p></div>
                <div><p class="text-slate-500">Meal rates</p><p class="text-xl font-semibold text-slate-900">{{ taka($rates['lunch']) }} / {{ taka($rates['dinner']) }}</p></div>
                <div><p class="text-slate-500">Due date</p><p class="text-xl font-semibold text-slate-900">{{ $dueDate->format('j M Y') }}</p></div>
            </div>
            <form method="POST" action="{{ route('manage.invoices.generate.store') }}" onsubmit="return confirm('Create {{ $newCount }} bills for {{ month_label($period) }}? Every student will be notified.')">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <button type="submit" class="btn btn-primary btn-lg" @disabled($newCount === 0)><x-icon name="sparkle" class="size-5" /> Create {{ $newCount }} bills</button>
            </form>
        </div>
    </div>

    <x-alert tone="info" class="mb-6" title="How the bill is calculated">
        Seat rent = room rent x days stayed / days in the month, rounded to whole taka. Meals = lunches x lunch rate + dinners x dinner rate, counting only the days the student held a seat.
        A student who changed rooms gets one rent line per room. Rates come from Hall settings.
    </x-alert>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Preview, {{ month_label($period) }}</h2><span class="text-sm text-slate-500">{{ $preview->count() }} students stayed in the hall</span></div>
        @if ($preview->isEmpty())
            <x-empty icon="receipt" title="Nobody lived in the hall in this month" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Lines</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr @class(['opacity-60' => $row['billed']])>
                                <td class="align-top">{{ $row['user']->name }}<span class="block text-xs text-slate-500">{{ $row['user']->profile?->student_id }}</span></td>
                                <td class="align-top">
                                    <ul class="space-y-0.5 text-xs text-slate-600">
                                        @foreach ($row['lines'] as $line)
                                            <li class="flex justify-between gap-6"><span>{{ $line['description'] }}</span><span class="tabular-nums">{{ taka($line['amount']) }}</span></li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="text-right align-top font-semibold tabular-nums">{{ taka($row['total']) }}</td>
                                <td class="align-top">
                                    @if ($row['billed'])
                                        <x-badge tone="gray">Already billed</x-badge>
                                    @else
                                        <x-badge tone="brand" dot>Will be created</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
