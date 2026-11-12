<x-layouts.app title="Bills and payments">
    <x-page-header title="Bills and payments" description="Monthly hall bills (seat rent and meals) and any other charges." />

    <section class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Total due" :value="taka($due)" icon="receipt" :tone="$overdue ? 'red' : 'brand'" :hint="$due ? 'Across all unpaid bills' : 'Nothing to pay right now'" />
        <x-stat label="Overdue" :value="taka($overdue)" icon="alert" :tone="$overdue ? 'red' : 'gray'" :hint="$overdue ? 'Past the due date, please pay soon' : 'You are on time'" />
        <x-stat label="Next due date" :value="$nextDue ? $nextDue->due_date->format('j M') : 'None'" icon="calendar" tone="blue" :hint="$nextDue ? $nextDue->title : 'All bills are paid'" />
    </section>

    <div class="card">
        <div class="card-header"><h2 class="card-title">All bills</h2></div>
        @if ($invoices->isEmpty())
            <x-empty icon="receipt" title="No bills yet">Your first monthly bill appears after your first month in the hall.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Bill</th><th>Due date</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('student.bills.show', $invoice) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $invoice->title }}</a>
                                    <span class="block text-xs text-slate-500">{{ $invoice->number }}</span>
                                </td>
                                <td @class(['text-rose-600 font-medium' => $invoice->isOverdue()])>
                                    {{ $invoice->due_date->format('j M Y') }}
                                    @if ($invoice->isOverdue()) <span class="block text-xs">Overdue</span> @endif
                                </td>
                                <td class="text-right tabular-nums">{{ taka($invoice->total) }}</td>
                                <td class="text-right tabular-nums">{{ taka($invoice->paid) }}</td>
                                <td class="text-right font-semibold tabular-nums text-slate-900">{{ taka($invoice->balance()) }}</td>
                                <td>
                                    <x-status :value="$invoice->status" />
                                    @if ($invoice->pending_sum)
                                        <span class="mt-1 block text-xs text-amber-700">{{ taka($invoice->pending_sum) }} being checked</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('student.bills.show', $invoice) }}" class="btn {{ $invoice->isOpen() ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $invoice->isOpen() ? 'Pay' : 'View' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $invoices->links() }}</div>
        @endif
    </div>
</x-layouts.app>
