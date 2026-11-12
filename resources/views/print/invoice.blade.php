<x-layouts.print :title="$invoice->number" :back="auth()->user()->isManager() ? route('manage.invoices.show', $invoice) : route('student.bills.show', $invoice)">
    @php $profile = $invoice->user->profile; @endphp
    <div class="mt-8 flex flex-wrap items-start justify-between gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-700">Bill</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $invoice->number }}</p>
            <p class="text-sm text-slate-600">{{ $invoice->title }}</p>
        </div>
        <div class="text-right text-sm">
            <p><span class="text-slate-500">Issued:</span> {{ $invoice->created_at->format('j F Y') }}</p>
            <p><span class="text-slate-500">Due:</span> <span class="font-semibold">{{ $invoice->due_date->format('j F Y') }}</span></p>
            <p class="mt-2"><span class="inline-block rounded-full border px-3 py-0.5 text-xs font-bold uppercase tracking-wide {{ $invoice->status->value === 'paid' ? 'border-emerald-600 text-emerald-700' : 'border-rose-600 text-rose-700' }}">{{ $invoice->status->label() }}</span></p>
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-slate-50 p-4 text-sm print:bg-white print:ring-1 print:ring-slate-200">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Billed to</p>
        <p class="mt-1 font-semibold text-slate-900">{{ $invoice->user->name }}</p>
        <p class="text-slate-600">Student ID {{ $profile?->student_id }}, {{ $profile?->department }}</p>
        <p class="text-slate-600">{{ $invoice->user->phone }}, {{ $invoice->user->email }}</p>
    </div>

    <table class="mt-6 w-full text-sm">
        <thead><tr class="border-b-2 border-slate-800 text-left text-xs uppercase tracking-wide text-slate-600"><th class="py-2">Description</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr class="border-b border-slate-200"><td class="py-2.5">{{ $item->description }}</td><td class="py-2.5 text-right tabular-nums">{{ $item->quantity }}</td><td class="py-2.5 text-right tabular-nums">{{ taka($item->unit_price) }}</td><td class="py-2.5 text-right tabular-nums">{{ taka($item->amount) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="pt-4 text-right text-slate-600">Total</td><td class="pt-4 text-right font-semibold tabular-nums">{{ taka($invoice->total) }}</td></tr>
            <tr><td colspan="3" class="pt-1 text-right text-slate-600">Paid</td><td class="pt-1 text-right tabular-nums">{{ taka($invoice->paid) }}</td></tr>
            <tr><td colspan="3" class="pt-2 text-right font-bold">Balance due</td><td class="pt-2 text-right text-lg font-bold tabular-nums">{{ taka($invoice->balance()) }}</td></tr>
        </tfoot>
    </table>

    @if ($invoice->balance() > 0)
        <div class="mt-8 rounded-lg border border-slate-300 p-4 text-sm">
            <p class="font-semibold text-slate-900">How to pay</p>
            <p class="mt-1 text-slate-600">bKash {{ setting('bkash_number') }}, Nagad {{ setting('nagad_number') }}, Rocket {{ setting('rocket_number') }}, or cash at the hall office. Bank: {{ setting('bank_account') }}. Use {{ $invoice->number }} as the reference and submit the transaction ID in the hall portal.</p>
        </div>
    @endif
</x-layouts.print>
