<x-layouts.app title="Payment checks">
    <x-page-header title="Payment checks" description="Compare each transaction ID with the bKash, Nagad, Rocket or bank statement, then verify or reject it." />

    <nav class="tabs mb-4">
        @foreach (\App\Enums\PaymentStatus::cases() as $s)
            <a href="{{ route('manage.payments.index', ['status' => $s->value]) }}" @class(['tab', 'tab-active' => $status === $s->value])>
                {{ $s === \App\Enums\PaymentStatus::Pending ? 'To verify' : $s->label() }} <span class="tab-count">{{ $counts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex items-end gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="w-72"><x-input name="q" label="Search" :value="$q" placeholder="Transaction ID, name, student ID" /></div>
            <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" /></button>
        </form>
        @if ($status === 'pending' && $pendingSum)
            <p class="text-sm text-slate-600">Waiting: <span class="font-semibold text-slate-900">{{ taka($pendingSum) }}</span></p>
        @endif
    </div>

    <div class="card">
        @if ($payments->isEmpty())
            <x-empty icon="check-circle" :title="$status === 'pending' ? 'Nothing to verify' : 'No payments here'">
                @if ($status === 'pending') New student payments will show up here. @endif
            </x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Bill</th><th>Method</th><th>Transaction ID</th><th class="text-right">Amount</th><th>{{ $status === 'pending' ? 'Sent' : 'Checked' }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr x-data="{ rejecting: false }">
                                <td>{{ $payment->user->name }}<span class="block text-xs text-slate-500">{{ $payment->user->profile?->student_id }}</span></td>
                                <td><a href="{{ route('manage.invoices.show', $payment->invoice) }}" class="text-slate-800 hover:text-brand-700">{{ $payment->invoice->number }}</a><span class="block text-xs text-slate-500">Balance {{ taka($payment->invoice->balance()) }}</span></td>
                                <td>{{ $payment->method->label() }}</td>
                                <td>
                                    <span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-semibold tracking-wide text-slate-800">{{ $payment->transaction_id ?? 'n/a' }}</span>
                                    @if ($payment->note)<span class="mt-1 block text-xs text-slate-500">{{ $payment->note }}</span>@endif
                                </td>
                                <td class="text-right text-base font-semibold tabular-nums text-slate-900">{{ taka($payment->amount) }}</td>
                                <td class="whitespace-nowrap text-slate-500">
                                    @if ($status === 'pending')
                                        {{ $payment->created_at->format('j M, g:i A') }}
                                    @else
                                        {{ $payment->verified_at?->format('j M, g:i A') }}<span class="block text-xs">{{ $payment->verifier?->name }}</span>
                                        @if ($payment->review_note)<span class="block text-xs text-rose-600">{{ $payment->review_note }}</span>@endif
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($payment->isPending())
                                        <div class="flex justify-end gap-2" x-show="!rejecting">
                                            <form method="POST" action="{{ route('manage.payments.verify', $payment) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm"><x-icon name="check" class="size-3.5" /> Verify</button>
                                            </form>
                                            <button type="button" class="btn btn-soft-danger btn-sm" x-on:click="rejecting = true">Reject</button>
                                        </div>
                                        <form method="POST" action="{{ route('manage.payments.reject', $payment) }}" class="flex justify-end gap-2" x-show="rejecting" x-cloak>
                                            @csrf
                                            <input type="text" name="reason" class="input w-48 py-1.5 text-xs" placeholder="Reason, e.g. TrxID not found" required>
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="rejecting = false" aria-label="Cancel"><x-icon name="x" class="size-3.5" /></button>
                                        </form>
                                    @elseif ($payment->status->value === 'verified')
                                        <a href="{{ route('receipts.show', $payment) }}" class="btn btn-ghost btn-sm"><x-icon name="printer" class="size-3.5" /> Receipt</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $payments->links() }}</div>
        @endif
    </div>
</x-layouts.app>
