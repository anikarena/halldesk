<?php

namespace App\Http\Controllers\Manage;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verification queue: staff compare each reported transaction ID with the
 * bKash / Nagad / bank statement, then verify or reject it.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $status = $filters['status'] ?? PaymentStatus::Pending->value;

        $payments = Payment::query()
            ->where('status', $status)
            ->with('user.profile', 'invoice', 'verifier')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('transaction_id', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->search($term))))
            ->orderBy($status === 'pending' ? 'created_at' : 'verified_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('manage.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'q' => $filters['q'] ?? null,
            'counts' => Payment::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'pendingSum' => (int) Payment::query()->where('status', PaymentStatus::Pending)->sum('amount'),
        ]);
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $this->payments->verify($payment, $request->user());

        return back()->with('success', taka($payment->amount)." from {$payment->user->name} was verified.");
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Write why it was rejected, the student will see it.']);

        $this->payments->reject($payment, $request->user(), $data['reason']);

        return back()->with('success', "The payment from {$payment->user->name} was rejected.");
    }
}
