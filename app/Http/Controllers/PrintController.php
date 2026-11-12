<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printer friendly documents: invoice, payment receipt, seat allotment letter.
 * A student can open only their own documents.
 */
class PrintController extends Controller
{
    public function invoice(Request $request, Invoice $invoice): View
    {
        $this->authorizeOwner($request, $invoice->user_id);

        return view('print.invoice', [
            'invoice' => $invoice->load('user.profile', 'items', 'payments'),
        ]);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $this->authorizeOwner($request, $payment->user_id);
        abort_unless($payment->status === PaymentStatus::Verified, 404);

        return view('print.receipt', [
            'payment' => $payment->load('user.profile', 'invoice', 'verifier'),
        ]);
    }

    public function allotment(Request $request, Allocation $allocation): View
    {
        $this->authorizeOwner($request, $allocation->user_id);

        return view('print.allotment', [
            'allocation' => $allocation->load('user.profile', 'seat.room', 'allocatedBy'),
        ]);
    }

    private function authorizeOwner(Request $request, int $ownerId): void
    {
        $user = $request->user();
        abort_unless($user->isManager() || $user->id === $ownerId, 403);
    }
}
