<?php

namespace App\Http\Controllers\Manage;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([...InvoiceStatus::values(), 'overdue', 'open'])],
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        $query = Invoice::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->search($term))))
            ->when($filters['period'] ?? null, fn ($q, $p) => $q->where('period', $p))
            ->when($filters['status'] ?? null, function ($q, $status) {
                match ($status) {
                    'overdue' => $q->overdue(),
                    'open' => $q->outstanding(),
                    default => $q->where('status', $status),
                };
            });

        $summary = (clone $query)->where('status', '!=', InvoiceStatus::Cancelled)
            ->selectRaw('count(*) as bills, coalesce(sum(total), 0) as billed, coalesce(sum(paid), 0) as collected')
            ->first();

        return view('manage.invoices.index', [
            'invoices' => $query->with('user.profile')->latest('due_date')->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'summary' => $summary,
            'periods' => Invoice::query()->whereNotNull('period')->distinct()->orderByDesc('period')->pluck('period'),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('user.profile', 'user.activeAllocation.seat.room', 'items', 'payments.verifier', 'payments.recorder', 'creator');

        return view('manage.invoices.show', [
            'invoice' => $invoice,
            'methods' => PaymentMethod::options(),
            'pending' => $invoice->pendingAmount(),
        ]);
    }

    /**
     * A one-off bill: admission fee, fine, damage charge...
     */
    public function create(Request $request): View
    {
        return view('manage.invoices.create', [
            'students' => User::query()->students()->where('is_active', true)->with('profile', 'activeAllocation.seat.room')->orderBy('name')->get(),
            'selectedStudent' => $request->integer('student') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'title' => ['required', 'string', 'max:120'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.description' => ['required', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.unit_price' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'items.*.description.required' => 'Every line needs a description.',
            'items.*.unit_price.min' => 'Every line needs a price of at least 1 taka.',
        ]);

        $lines = array_map(fn ($item) => [
            'description' => $item['description'],
            'quantity' => (int) $item['quantity'],
            'unit_price' => (int) $item['unit_price'],
            'amount' => (int) $item['quantity'] * (int) $item['unit_price'],
        ], array_values($data['items']));

        $invoice = $this->billing->createInvoice(
            User::query()->findOrFail($data['user_id']),
            $data['title'],
            $lines,
            CarbonImmutable::parse($data['due_date']),
            $request->user(),
        );

        return redirect()->route('manage.invoices.show', $invoice)->with('success', "Bill {$invoice->number} for ".taka($invoice->total).' was created.');
    }

    /**
     * Step 1: choose a month and preview every student's bill.
     */
    public function generateForm(Request $request): View
    {
        $periods = $this->billing->billablePeriods();
        $request->validate(['period' => ['nullable', Rule::in($periods)]]);
        $period = $request->query('period', $periods[0]);

        $preview = $this->billing->preview($period);

        return view('manage.invoices.generate', [
            'periods' => $periods,
            'period' => $period,
            'preview' => $preview,
            'newCount' => $preview->where('billed', false)->where('total', '>', 0)->count(),
            'newTotal' => $preview->where('billed', false)->sum('total'),
            'dueDate' => $this->billing->dueDateFor($period),
            'rates' => ['lunch' => (int) setting('lunch_rate'), 'dinner' => (int) setting('dinner_rate')],
        ]);
    }

    /**
     * Step 2: create the bills.
     */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', Rule::in($this->billing->billablePeriods())]]);

        $result = DB::transaction(fn () => $this->billing->generate($data['period'], $request->user()));

        $message = $result['created'] > 0
            ? "{$result['created']} bills for ".month_label($data['period']).' were created, '.taka($result['amount']).' in total.'
            : 'No new bills were needed for '.month_label($data['period']).'.';
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped because they were already billed.";
        }

        return redirect()->route('manage.invoices.index', ['period' => $data['period']])->with('success', $message);
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $this->billing->cancel($invoice);

        return back()->with('success', "Bill {$invoice->number} was cancelled.");
    }

    public function recordPayment(Request $request, Invoice $invoice, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'transaction_id' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $payments->record(
            $invoice,
            $request->user(),
            (int) $data['amount'],
            PaymentMethod::from($data['method']),
            $data['transaction_id'] ?? null,
            $data['note'] ?? null,
        );

        return back()->with('success', taka($payment->amount).' received. Receipt '.$payment->receiptNumber().' is ready to print.');
    }
}
