<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="text-slate-800">{{ $item->description }}</td>
                    <td class="text-right tabular-nums">{{ $item->quantity }}</td>
                    <td class="text-right tabular-nums">{{ taka($item->unit_price) }}</td>
                    <td class="text-right font-medium tabular-nums text-slate-900">{{ taka($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="text-sm">
            <tr><td colspan="3" class="px-4 pt-4 text-right text-slate-500">Total</td><td class="px-4 pt-4 text-right font-semibold tabular-nums text-slate-900">{{ taka($invoice->total) }}</td></tr>
            <tr><td colspan="3" class="px-4 pt-1 text-right text-slate-500">Paid</td><td class="px-4 pt-1 text-right tabular-nums text-emerald-700">{{ taka($invoice->paid) }}</td></tr>
            <tr><td colspan="3" class="px-4 pb-4 pt-1 text-right font-semibold text-slate-800">Balance</td><td class="px-4 pb-4 pt-1 text-right text-base font-bold tabular-nums text-slate-900">{{ taka($invoice->balance()) }}</td></tr>
        </tfoot>
    </table>
</div>
