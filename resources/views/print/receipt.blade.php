<x-layouts.print :title="$payment->receiptNumber()">
    <div class="mt-8 text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-700">Money receipt</p>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $payment->receiptNumber() }}</p>
        <p class="text-sm text-slate-500">{{ $payment->verified_at?->format('j F Y, g:i A') }}</p>
    </div>

    <div class="mx-auto mt-8 max-w-lg rounded-xl border-2 border-dashed border-brand-300 p-6 text-center">
        <p class="text-sm text-slate-500">Amount received</p>
        <p class="mt-1 text-4xl font-bold tracking-tight text-slate-900">{{ taka($payment->amount) }}</p>
        <p class="mt-1 text-sm text-slate-600">by {{ $payment->method->label() }}{{ $payment->transaction_id ? ', transaction ID '.$payment->transaction_id : '' }}</p>
    </div>

    <dl class="mx-auto mt-8 max-w-lg divide-y divide-slate-200 text-sm">
        <div class="flex justify-between py-2.5"><dt class="text-slate-500">Received from</dt><dd class="font-medium">{{ $payment->user->name }}</dd></div>
        <div class="flex justify-between py-2.5"><dt class="text-slate-500">Student ID</dt><dd class="font-medium">{{ $payment->user->profile?->student_id }}</dd></div>
        <div class="flex justify-between py-2.5"><dt class="text-slate-500">For bill</dt><dd class="font-medium">{{ $payment->invoice->number }}, {{ $payment->invoice->title }}</dd></div>
        <div class="flex justify-between py-2.5"><dt class="text-slate-500">Bill balance after this payment</dt><dd class="font-medium">{{ taka($payment->invoice->balance()) }} today</dd></div>
        <div class="flex justify-between py-2.5"><dt class="text-slate-500">Verified by</dt><dd class="font-medium">{{ $payment->verifier?->name ?? 'Hall office' }}</dd></div>
    </dl>

    <div class="mt-16 flex justify-end">
        <div class="w-56 border-t border-slate-400 pt-2 text-center text-xs text-slate-500">Hall office seal and signature</div>
    </div>
</x-layouts.print>
