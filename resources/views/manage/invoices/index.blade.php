<x-layouts.app title="Bills">
    <x-page-header title="Bills" description="Monthly hall bills and one-off charges for every student.">
        @if (auth()->user()->isAdmin())
            <x-slot:actions>
                <a href="{{ route('manage.reports.dues') }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> Dues list (CSV)</a>
                <a href="{{ route('manage.invoices.create') }}" class="btn btn-secondary"><x-icon name="plus" class="size-4" /> One-off bill</a>
                <a href="{{ route('manage.invoices.generate') }}" class="btn btn-primary"><x-icon name="sparkle" class="size-4" /> Generate monthly bills</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @php $status = $filters['status'] ?? null; @endphp
    <nav class="tabs mb-4">
        @foreach ([null => 'All', 'open' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $key => $label)
            <a href="{{ route('manage.invoices.index', array_filter(['status' => $key ?: null, 'period' => $filters['period'] ?? null, 'q' => $filters['q'] ?? null])) }}"
               @class(['tab', 'tab-active' => $status === ($key ?: null)])>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="w-full sm:w-72"><x-input name="q" label="Search" :value="$filters['q'] ?? null" placeholder="Bill number, name, student ID" /></div>
        <div class="w-48"><x-select name="period" label="Billing month" placeholder="Any month" :value="$filters['period'] ?? null" :options="$periods->mapWithKeys(fn ($p) => [$p => month_label($p)])->all()" /></div>
        <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" /> Search</button>
    </form>

    <section class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-stat label="Bills in this view" :value="number_format($summary->bills)" icon="receipt" tone="gray" />
        <x-stat label="Billed" :value="taka($summary->billed)" icon="file" tone="blue" />
        <x-stat label="Collected" :value="taka($summary->collected)" icon="banknote" :hint="'Balance '.taka($summary->billed - $summary->collected)" />
    </section>

    <div class="card">
        @if ($invoices->isEmpty())
            <x-empty icon="receipt" title="No bills found" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Bill</th><th>Student</th><th>Due date</th><th class="text-right">Total</th><th class="text-right">Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td><a href="{{ route('manage.invoices.show', $invoice) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $invoice->title }}</a><span class="block font-mono text-xs text-slate-500">{{ $invoice->number }}</span></td>
                                <td>{{ $invoice->user->name }}<span class="block text-xs text-slate-500">{{ $invoice->user->profile?->student_id }}</span></td>
                                <td @class(['whitespace-nowrap', 'font-medium text-rose-600' => $invoice->isOverdue()])>{{ $invoice->due_date->format('j M Y') }}@if ($invoice->isOverdue())<span class="block text-xs">Overdue</span>@endif</td>
                                <td class="text-right tabular-nums">{{ taka($invoice->total) }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ taka($invoice->balance()) }}</td>
                                <td><x-status :value="$invoice->status" /></td>
                                <td class="text-right"><a href="{{ route('manage.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $invoices->links() }}</div>
        @endif
    </div>
</x-layouts.app>
