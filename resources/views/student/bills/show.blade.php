<x-layouts.app :title="$invoice->title">
    <x-page-header :title="$invoice->title" :back="route('student.bills.index')" back-label="All bills"
                   :description="$invoice->number.', issued '.$invoice->created_at->format('j M Y').', due '.$invoice->due_date->format('j M Y')">
        <x-slot:actions>
            <x-status :value="$invoice->status" />
            <a href="{{ route('print.invoice', $invoice) }}" class="btn btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
        </x-slot:actions>
    </x-page-header>

    @if ($invoice->isOverdue())
        <x-alert tone="danger" class="mb-6" title="This bill is overdue">The due date was {{ $invoice->due_date->format('j F Y') }}. Please pay the balance as soon as possible.</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Bill details</h2></div>
                @include('partials.invoice-items', ['invoice' => $invoice])
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Payments for this bill</h2></div>
                @include('partials.payment-list', ['payments' => $invoice->payments])
            </div>
        </div>

        <div class="lg:col-span-2">
            @if ($invoice->isOpen() && $payable > 0)
                <form method="POST" action="{{ route('student.bills.pay', $invoice) }}" class="card"
                      x-data="{ method: @js(old('method', 'bkash')) }">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Pay this bill</h2>
                            <p class="text-xs text-slate-500">Up to {{ taka($payable) }}</p>
                        </div>
                    </div>
                    <div class="card-body space-y-4">
                        <ol class="space-y-2 rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
                            <li><span class="font-semibold">1.</span> Send money to the hall account below.</li>
                            <li><span class="font-semibold">2.</span> Copy the transaction ID from the SMS.</li>
                            <li><span class="font-semibold">3.</span> Fill this form. The office verifies it, usually within a day.</li>
                        </ol>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($methods as $value => $label)
                                <label class="flex cursor-pointer items-center justify-center rounded-lg border px-3 py-2 text-sm font-medium"
                                       x-bind:class="method === '{{ $value }}' ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-500' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                                    <input type="radio" name="method" value="{{ $value }}" class="sr-only" x-model="method"> {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-brand-700">Send to</p>
                            <p class="mt-0.5 font-semibold text-brand-950" x-show="method === 'bkash'">bKash merchant: {{ setting('bkash_number') }}</p>
                            <p class="mt-0.5 font-semibold text-brand-950" x-show="method === 'nagad'" x-cloak>Nagad merchant: {{ setting('nagad_number') }}</p>
                            <p class="mt-0.5 font-semibold text-brand-950" x-show="method === 'rocket'" x-cloak>Rocket: {{ setting('rocket_number') }}</p>
                            <p class="mt-0.5 font-semibold text-brand-950" x-show="method === 'bank'" x-cloak>{{ setting('bank_account') }}</p>
                            <p class="mt-1 text-xs text-brand-800">Write {{ $invoice->number }} as the reference.</p>
                        </div>

                        <x-input name="amount" type="number" label="Amount you sent (৳)" required min="1" :max="$payable" :value="$payable" />
                        <x-input name="transaction_id" label="Transaction ID" required placeholder="e.g. 9K7D2XQ4LM" autocomplete="off" class="uppercase placeholder:normal-case" />
                        <x-input name="note" label="Note (optional)" placeholder="Sender number or anything else" />
                    </div>
                    <div class="border-t border-slate-100 px-5 py-4">
                        <button type="submit" class="btn btn-primary w-full">Submit payment</button>
                    </div>
                </form>
            @elseif ($invoice->isOpen())
                <x-alert tone="warning" title="Payment is being verified">
                    You have reported {{ taka($pending) }} for this bill. The office will verify it soon, you will get a notification.
                </x-alert>
            @elseif ($invoice->status->value === 'paid')
                <div class="card p-6 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check" class="size-6" /></span>
                    <p class="mt-3 font-semibold text-slate-900">Fully paid</p>
                    <p class="text-sm text-slate-500">Thank you. Receipts are listed with each payment.</p>
                </div>
            @else
                <x-alert tone="info">This bill was cancelled by the hall office. Nothing to pay.</x-alert>
            @endif
        </div>
    </div>
</x-layouts.app>
