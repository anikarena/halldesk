<?php

namespace App\Http\Controllers\Student;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->withSum(['payments as pending_sum' => fn ($q) => $q->where('status', 'pending')], 'amount')
            ->latest('due_date')
            ->latest('id')
            ->paginate(12);

        $open = Invoice::query()->where('user_id', $request->user()->id)->outstanding()->get();

        return view('student.bills.index', [
            'invoices' => $invoices,
            'due' => $open->sum(fn (Invoice $i) => $i->balance()),
            'overdue' => $open->filter(fn (Invoice $i) => $i->isOverdue())->sum(fn (Invoice $i) => $i->balance()),
            'nextDue' => $open->sortBy('due_date')->first(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load('items', 'payments');
        $pending = $invoice->pendingAmount();

        return view('student.bills.show', [
            'invoice' => $invoice,
            'pending' => $pending,
            'payable' => max(0, $invoice->balance() - $pending),
            'methods' => PaymentMethod::online(),
        ]);
    }

    public function pay(Request $request, Invoice $invoice, PaymentService $payments): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(array_keys(PaymentMethod::online()))],
            'transaction_id' => ['required', 'string', 'min:6', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'transaction_id.regex' => 'The transaction ID has only letters and numbers, copy it from your payment SMS.',
        ]);

        $payment = $payments->submit(
            $invoice,
            $request->user(),
            (int) $data['amount'],
            PaymentMethod::from($data['method']),
            $data['transaction_id'],
            $data['note'] ?? null,
        );

        return back()->with('success', 'Thank you. '.taka($payment->amount).' has been sent for verification. You will get a notification once the office checks it.');
    }
}
