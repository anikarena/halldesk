<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\HallException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = app(PaymentService::class);
    }

    public function test_a_reported_payment_changes_the_bill_only_after_verification(): void
    {
        [$student, $invoice] = $this->bill(5000);

        $payment = $this->payments->submit($invoice, $student, 5000, PaymentMethod::Bkash, 'abc123xyz9');
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertSame('ABC123XYZ9', $payment->transaction_id);

        $this->payments->verify($payment, $this->staffMember());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(5000, $invoice->paid);
        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
    }

    public function test_a_part_payment_marks_the_bill_partly_paid(): void
    {
        [, $invoice] = $this->bill(5000);

        $this->payments->record($invoice, $this->staffMember(), 2000, PaymentMethod::Cash);

        $this->assertSame(InvoiceStatus::Partial, $invoice->fresh()->status);
        $this->assertSame(3000, $invoice->fresh()->balance());
    }

    public function test_a_student_cannot_report_more_than_the_balance(): void
    {
        [$student, $invoice] = $this->bill(5000);
        $this->payments->submit($invoice, $student, 3000, PaymentMethod::Nagad, 'TRX0000001');

        // 2000 left after the pending 3000
        $this->expectException(HallException::class);
        $this->payments->submit($invoice, $student, 2500, PaymentMethod::Nagad, 'TRX0000002');
    }

    public function test_a_transaction_id_can_be_used_only_once(): void
    {
        [$student, $invoice] = $this->bill(5000);
        $this->payments->submit($invoice, $student, 1000, PaymentMethod::Bkash, 'SAMEID1234');

        $this->expectException(HallException::class);
        $this->payments->submit($invoice, $student, 1000, PaymentMethod::Bkash, 'sameid1234');
    }

    public function test_a_rejected_transaction_id_can_be_sent_again(): void
    {
        [$student, $invoice] = $this->bill(5000);
        $first = $this->payments->submit($invoice, $student, 5000, PaymentMethod::Bkash, 'TYPO123456');
        $this->payments->reject($first, $this->staffMember(), 'Amount did not match');

        $second = $this->payments->submit($invoice, $student, 5000, PaymentMethod::Bkash, 'TYPO123456');

        $this->assertTrue($second->isPending());
    }

    public function test_a_student_pays_from_the_bill_page(): void
    {
        [$student, $invoice] = $this->bill(4000);

        $this->actingAs($student)
            ->post(route('student.bills.pay', $invoice), ['amount' => 4000, 'method' => 'bkash', 'transaction_id' => 'PAGE123456'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'status' => 'pending', 'amount' => 4000]);
    }

    public function test_a_student_cannot_open_someone_elses_bill(): void
    {
        [, $invoice] = $this->bill(4000);

        $this->actingAs($this->student())->get(route('student.bills.show', $invoice))->assertForbidden();
        $this->actingAs($this->student())->get(route('print.invoice', $invoice))->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Invoice}
     */
    private function bill(int $amount): array
    {
        $student = $this->student();
        $invoice = app(BillingService::class)->createInvoice(
            $student,
            'Test bill',
            [['description' => 'Charge', 'quantity' => 1, 'unit_price' => $amount, 'amount' => $amount]],
            today()->addDays(10),
            $this->admin(),
        );

        return [$student, $invoice];
    }
}
