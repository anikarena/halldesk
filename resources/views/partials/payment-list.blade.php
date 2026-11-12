<ul class="divide-y divide-slate-100">
    @forelse ($payments as $payment)
        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
            <span @class([
                'grid size-9 shrink-0 place-items-center rounded-lg',
                'bg-emerald-50 text-emerald-700' => $payment->status->value === 'verified',
                'bg-amber-50 text-amber-700' => $payment->status->value === 'pending',
                'bg-rose-50 text-rose-700' => $payment->status->value === 'rejected',
            ])><x-icon name="wallet" class="size-4" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-slate-800">{{ taka($payment->amount) }} <span class="font-normal text-slate-500">by {{ $payment->method->label() }}</span></span>
                <span class="block text-xs text-slate-500">
                    {{ $payment->created_at->format('j M Y, g:i A') }}{{ $payment->transaction_id ? ', TrxID '.$payment->transaction_id : '' }}
                </span>
                @if ($payment->review_note)
                    <span class="mt-0.5 block text-xs text-rose-600">Reason: {{ $payment->review_note }}</span>
                @endif
            </span>
            <x-status :value="$payment->status" />
            @if ($payment->status->value === 'verified')
                <a href="{{ route('receipts.show', $payment) }}" class="btn btn-ghost btn-sm"><x-icon name="printer" class="size-3.5" /> Receipt</a>
            @endif
        </li>
    @empty
        <li><x-empty icon="wallet" title="No payments yet" /></li>
    @endforelse
</ul>
