<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\HallException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\HallAlert;
use Illuminate\Support\Facades\DB;

/**
 * Payment flow.
 *
 *  Online (bKash, Nagad, Rocket, bank):
 *    student pays to the hall number, then submits amount + transaction ID
 *    -> payment is "pending" -> staff check their statement and verify or reject.
 *  Cash: staff record it at the office, it is verified at once.
 *
 * The invoice balance changes only when a payment is verified. A student can
 * never report more than the unpaid balance minus what is already pending,
 * and one transaction ID can be used only once.
 */
class PaymentService
{
    public function submit(Invoice $invoice, User $student, int $amount, PaymentMethod $method, string $transactionId, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($invoice, $student, $amount, $method, $transactionId, $note) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->user_id !== $student->id) {
                throw new HallException('This bill belongs to another student.');
            }
            $this->assertCanReceive($invoice, $amount, $invoice->pendingAmount());
            $this->assertNewTransactionId($transactionId);

            return Payment::query()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $student->id,
                'amount' => $amount,
                'method' => $method,
                'transaction_id' => strtoupper(trim($transactionId)),
                'status' => PaymentStatus::Pending,
                'note' => $note,
            ]);
        });
    }

    /**
     * Money received in person (or a bank slip checked at the office).
     */
    public function record(Invoice $invoice, User $staff, int $amount, PaymentMethod $method, ?string $transactionId = null, ?string $note = null): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $staff, $amount, $method, $transactionId, $note) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $this->assertCanReceive($invoice, $amount, 0);
            if ($transactionId) {
                $this->assertNewTransactionId($transactionId);
            }

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'amount' => $amount,
                'method' => $method,
                'transaction_id' => $transactionId ? strtoupper(trim($transactionId)) : null,
                'status' => PaymentStatus::Verified,
                'note' => $note,
                'recorded_by' => $staff->id,
                'verified_by' => $staff->id,
                'verified_at' => now(),
            ]);
            $this->applyToInvoice($invoice, $amount);

            return $payment;
        });

        $this->notifyVerified($payment);

        return $payment;
    }

    public function verify(Payment $payment, User $staff): void
    {
        $payment = DB::transaction(function () use ($payment, $staff) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            if (! $payment->isPending()) {
                throw new HallException('This payment was already '.strtolower($payment->status->label()).'.');
            }
            if ($payment->amount > $invoice->balance()) {
                throw new HallException('This payment is more than the remaining balance of '.taka($invoice->balance()).'. Reject it and ask the student to contact the office.');
            }

            $payment->update([
                'status' => PaymentStatus::Verified,
                'verified_by' => $staff->id,
                'verified_at' => now(),
            ]);
            $this->applyToInvoice($invoice, $payment->amount);

            return $payment;
        });

        $this->notifyVerified($payment);
    }

    public function reject(Payment $payment, User $staff, string $reason): void
    {
        DB::transaction(function () use ($payment, $staff, $reason) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! $payment->isPending()) {
                throw new HallException('Only a payment that is waiting for verification can be rejected.');
            }

            $payment->update([
                'status' => PaymentStatus::Rejected,
                'review_note' => $reason,
                'verified_by' => $staff->id,
                'verified_at' => now(),
            ]);
        });

        $payment->refresh()->load('invoice');
        $payment->user->notify(new HallAlert(
            'Payment rejected',
            taka($payment->amount)." with transaction ID {$payment->transaction_id} could not be verified. Reason: {$reason}",
            route('student.bills.show', $payment->invoice),
            'danger',
        ));
    }

    private function assertCanReceive(Invoice $invoice, int $amount, int $alreadyPending): void
    {
        if (! $invoice->isOpen()) {
            throw new HallException('This bill is '.strtolower($invoice->status->label()).', no payment is needed.');
        }

        $payable = $invoice->balance() - $alreadyPending;
        if ($payable <= 0) {
            throw new HallException('Your earlier payments for this bill are still being verified. Please wait for the office.');
        }
        if ($amount < 1 || $amount > $payable) {
            throw new HallException('The amount must be between '.taka(1).' and '.taka($payable).'.');
        }
    }

    private function assertNewTransactionId(string $transactionId): void
    {
        $used = Payment::query()
            ->where('transaction_id', strtoupper(trim($transactionId)))
            ->where('status', '!=', PaymentStatus::Rejected)
            ->exists();

        if ($used) {
            throw new HallException('This transaction ID has already been used for another payment.');
        }
    }

    private function applyToInvoice(Invoice $invoice, int $amount): void
    {
        $invoice->paid += $amount;
        $invoice->status = $invoice->paid >= $invoice->total ? InvoiceStatus::Paid : InvoiceStatus::Partial;
        $invoice->save();
    }

    private function notifyVerified(Payment $payment): void
    {
        $payment->load('invoice', 'user');
        $balance = $payment->invoice->balance();

        $payment->user->notify(new HallAlert(
            'Payment received: '.taka($payment->amount),
            $balance > 0
                ? "Thank you. {$payment->invoice->title} still has ".taka($balance).' to pay.'
                : "Thank you. {$payment->invoice->title} is now fully paid.",
            route('receipts.show', $payment),
            'success',
        ));
    }
}
