<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Assistant\Tool;

class MyBills extends Tool
{
    public function name(): string
    {
        return 'my_bills';
    }

    public function description(): string
    {
        return 'The student\'s unpaid bills (amount due, due date, overdue or not), payments waiting for the office to verify, '
            .'recent payments and the numbers to pay to.';
    }

    public function allows(User $user): bool
    {
        return $user->isStudent();
    }

    public function run(User $user, array $args): array
    {
        $open = Invoice::query()->where('user_id', $user->id)->outstanding()->orderBy('due_date')->get();
        $payments = Payment::query()->where('user_id', $user->id)->with('invoice')->latest()->limit(3)->get();

        return [
            'total_due' => taka($open->sum(fn (Invoice $invoice) => $invoice->balance())),
            'unpaid_bills' => $open->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'title' => $invoice->title,
                'total' => taka($invoice->total),
                'paid' => taka($invoice->paid),
                'due' => taka($invoice->balance()),
                'due_date' => $invoice->due_date->format('D, j M Y'),
                'overdue' => $invoice->isOverdue(),
                'waiting_for_verification' => taka($invoice->pendingAmount()),
            ])->all(),
            'recent_payments' => $payments->map(fn (Payment $payment) => [
                'amount' => taka($payment->amount),
                'method' => $payment->method->label(),
                'status' => $payment->status->label(),
                'date' => $payment->created_at->format('D, j M Y'),
                'bill' => $payment->invoice?->number,
            ])->all(),
            'pay_to' => [
                'bkash' => setting('bkash_number'),
                'nagad' => setting('nagad_number'),
                'rocket' => setting('rocket_number'),
                'bank' => setting('bank_account'),
                'how' => 'Pay, then open Bills and payments, choose the bill and enter the amount and transaction ID. The office verifies it.',
            ],
            '_links' => [['label' => 'Bills and payments', 'url' => route('student.bills.index')]],
        ];
    }
}
