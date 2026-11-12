<x-layouts.app :title="$invoice->number">
    <x-page-header :title="$invoice->title" :back="route('manage.invoices.index')" back-label="Bills"
                   :description="$invoice->number.', '.$invoice->user->name.' ('.($invoice->user->profile?->student_id ?? 'no ID').'), due '.$invoice->due_date->format('j M Y')">
        <x-slot:actions>
            <x-status :value="$invoice->status" />
            <a href="{{ route('print.invoice', $invoice) }}" class="btn btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
            @if (auth()->user()->isAdmin() && $invoice->isOpen() && $invoice->paid === 0)
                <form method="POST" action="{{ route('manage.invoices.cancel', $invoice) }}" onsubmit="return confirm('Cancel bill {{ $invoice->number }}? The student will no longer need to pay it.')">
                    @csrf
                    <button type="submit" class="btn btn-soft-danger">Cancel bill</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($invoice->isOverdue())
        <x-alert tone="danger" class="mb-6" title="Overdue">{{ taka($invoice->balance()) }} was due on {{ $invoice->due_date->format('j F Y') }}.</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Bill details</h2>@if ($invoice->creator)<span class="text-xs text-slate-500">Created by {{ $invoice->creator->name }}</span>@endif</div>
                @include('partials.invoice-items', ['invoice' => $invoice])
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Payments</h2>@if ($pending)<x-badge tone="amber">{{ taka($pending) }} waiting for verification</x-badge>@endif</div>
                @include('partials.payment-list', ['payments' => $invoice->payments])
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="card p-5">
                <div class="flex items-center gap-3">
                    <x-avatar :user="$invoice->user" />
                    <div class="min-w-0">
                        <a href="{{ route('manage.students.show', $invoice->user) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-700">{{ $invoice->user->name }}</a>
                        <p class="text-xs text-slate-500">{{ $invoice->user->activeAllocation ? 'Seat '.$invoice->user->activeAllocation->seat->code() : 'Not in the hall now' }}, {{ $invoice->user->phone }}</p>
                    </div>
                </div>
            </div>

            @if ($invoice->isOpen() && $invoice->balance() > 0)
                <form method="POST" action="{{ route('manage.invoices.payments.store', $invoice) }}" class="card">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Receive a payment</h2>
                            <p class="text-xs text-slate-500">Cash at the office, or a bank slip you have checked. It is verified immediately.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <x-input name="amount" type="number" label="Amount (৳)" required min="1" :max="$invoice->balance()" :value="$invoice->balance()" />
                            <x-select name="method" label="Method" required value="cash" :options="$methods" />
                        </div>
                        <x-input name="transaction_id" label="Transaction or slip number" hint="Not needed for cash." />
                        <x-input name="note" label="Note" placeholder="Optional" />
                    </div>
                    <div class="border-t border-slate-100 px-5 py-4">
                        <button type="submit" class="btn btn-primary w-full"><x-icon name="banknote" class="size-4" /> Record payment</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
