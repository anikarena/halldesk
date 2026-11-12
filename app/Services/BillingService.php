<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\HallException;
use App\Models\Allocation;
use App\Models\Invoice;
use App\Models\MealOff;
use App\Models\User;
use App\Notifications\HallAlert;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monthly hall bill.
 *
 *   seat rent   = room rent x days stayed / days in month   (rounded to whole taka)
 *   electricity = electricity charge x days stayed / days in month
 *   meals       = lunches x lunch rate + dinners x dinner rate
 *
 * A student who moved rooms during the month gets one rent line per room.
 * Bills are made only for months that have ended, and at most once per
 * student per month (unique index on invoices.user_id + kind + period),
 * so pressing "Generate" twice never bills anyone twice.
 */
class BillingService
{
    public function __construct(private MealService $meals) {}

    public function canGenerate(string $period): bool
    {
        return $this->monthEnd($period)->lt(CarbonImmutable::today());
    }

    /**
     * Bill lines for every student who stayed in the hall during $period.
     *
     * @return Collection<int, array{user: User, lines: list<array{description: string, quantity: int, unit_price: int, amount: int}>, total: int, billed: bool}>
     */
    public function preview(string $period): Collection
    {
        $from = $this->monthStart($period);
        $to = $this->monthEnd($period);

        $allocations = Allocation::query()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->with(['seat.room', 'user.profile'])
            ->orderBy('start_date')
            ->get()
            ->groupBy('user_id');

        $offs = MealOff::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('user_id', $allocations->keys())
            ->get()
            ->groupBy('user_id');

        $billed = Invoice::query()
            ->where('kind', Invoice::KIND_MONTHLY)
            ->where('period', $period)
            ->pluck('user_id')
            ->flip();

        return $allocations->map(function (Collection $userAllocations, int $userId) use ($period, $offs, $billed) {
            $lines = $this->linesFor($period, $userAllocations, $offs->get($userId, collect()));

            return [
                'user' => $userAllocations->first()->user,
                'lines' => $lines,
                'total' => array_sum(array_column($lines, 'amount')),
                'billed' => $billed->has($userId),
            ];
        })->sortBy(fn ($row) => $row['user']->name)->values();
    }

    /**
     * Create the monthly bill for everyone who does not have one yet.
     *
     * @return array{created: int, skipped: int, amount: int}
     */
    public function generate(string $period, User $by): array
    {
        if (! $this->canGenerate($period)) {
            throw new HallException('Bills can be generated only after the month has ended. '.month_label($period).' is not over yet.');
        }

        $result = ['created' => 0, 'skipped' => 0, 'amount' => 0];
        $dueDate = $this->dueDateFor($period);

        foreach ($this->preview($period) as $row) {
            if ($row['billed'] || $row['total'] === 0) {
                $result['skipped']++;

                continue;
            }

            $invoice = $this->createInvoice(
                $row['user'],
                'Hall bill, '.month_label($period),
                $row['lines'],
                $dueDate,
                $by,
                Invoice::KIND_MONTHLY,
                $period,
            );

            $result['created']++;
            $result['amount'] += $invoice->total;
        }

        return $result;
    }

    /**
     * @param  Collection<int, Allocation>  $allocations  this student's allocations that overlap the month
     * @param  Collection<int, MealOff>  $offs  this student's off meals in the month
     * @return list<array{description: string, quantity: int, unit_price: int, amount: int}>
     */
    public function linesFor(string $period, Collection $allocations, Collection $offs): array
    {
        $from = $this->monthStart($period);
        $to = $this->monthEnd($period);
        $daysInMonth = $from->daysInMonth;
        $lines = [];
        $stayed = 0;

        foreach ($allocations as $allocation) {
            $start = $allocation->start_date->max($from);
            $end = ($allocation->end_date ?? $to)->min($to);
            if ($start->gt($end)) {
                continue;
            }

            $days = (int) $start->diffInDays($end) + 1;
            $stayed += $days;
            $room = $allocation->seat->room;
            $rent = (int) round($room->monthly_rent * $days / $daysInMonth);

            $lines[] = [
                'description' => $days === $daysInMonth
                    ? "Seat rent, seat {$room->number}-{$allocation->seat->label} (full month)"
                    : "Seat rent, seat {$room->number}-{$allocation->seat->label} ({$days} of {$daysInMonth} days)",
                'quantity' => 1,
                'unit_price' => $rent,
                'amount' => $rent,
            ];
        }

        // One electricity line for the whole stay, even after a room transfer
        $stayed = min($stayed, $daysInMonth);
        $electricity = (int) round((int) setting('electricity_charge') * $stayed / $daysInMonth);
        if ($electricity > 0) {
            $lines[] = [
                'description' => $stayed === $daysInMonth ? 'Electricity (full month)' : "Electricity ({$stayed} of {$daysInMonth} days)",
                'quantity' => 1,
                'unit_price' => $electricity,
                'amount' => $electricity,
            ];
        }

        $counts = $this->meals->countMeals(
            $this->meals->residentDays($allocations, $from, $to),
            $this->meals->keyOffs($offs),
        );

        foreach (['lunch' => 'Lunch', 'dinner' => 'Dinner'] as $meal => $label) {
            $rate = (int) setting($meal.'_rate');
            if ($counts[$meal] > 0) {
                $lines[] = [
                    'description' => "{$label}, {$counts[$meal]} meals",
                    'quantity' => $counts[$meal],
                    'unit_price' => $rate,
                    'amount' => $counts[$meal] * $rate,
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_price: int, amount: int}>  $lines
     */
    public function createInvoice(
        User $student,
        string $title,
        array $lines,
        CarbonInterface $dueDate,
        User $by,
        string $kind = Invoice::KIND_OTHER,
        ?string $period = null,
    ): Invoice {
        $invoice = DB::transaction(function () use ($student, $title, $lines, $dueDate, $by, $kind, $period) {
            $invoice = Invoice::query()->create([
                'user_id' => $student->id,
                'kind' => $kind,
                'period' => $period,
                'title' => $title,
                'due_date' => $dueDate,
                'total' => array_sum(array_column($lines, 'amount')),
                'status' => InvoiceStatus::Unpaid,
                'created_by' => $by->id,
            ]);
            $invoice->items()->createMany($lines);

            return $invoice;
        });

        $student->notify(new HallAlert(
            'New bill: '.taka($invoice->total),
            "{$title}. Please pay by {$invoice->due_date->format('j M Y')}.",
            route('student.bills.show', $invoice),
            'warning',
        ));

        return $invoice;
    }

    /**
     * Cancel a bill that was made by mistake. Only possible before any money was received.
     */
    public function cancel(Invoice $invoice): void
    {
        if ($invoice->paid > 0 || $invoice->payments()->where('status', PaymentStatus::Pending)->exists()) {
            throw new HallException('This bill already has payments, so it cannot be cancelled.');
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled]);
    }

    /**
     * Bills are due on the "bill_due_day" of the month after the billing month.
     */
    public function dueDateFor(string $period): CarbonImmutable
    {
        $next = $this->monthStart($period)->addMonthNoOverflow();
        $day = min(max(1, (int) setting('bill_due_day', 10)), $next->daysInMonth);

        return $next->setDay($day);
    }

    /**
     * Months that can be billed now, newest first.
     *
     * @return list<string>
     */
    public function billablePeriods(int $count = 6): array
    {
        $periods = [];
        $month = CarbonImmutable::today()->startOfMonth()->subMonthNoOverflow();
        for ($i = 0; $i < $count; $i++) {
            $periods[] = $month->format('Y-m');
            $month = $month->subMonthNoOverflow();
        }

        return $periods;
    }

    private function monthStart(string $period): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period)->startOfMonth();
    }

    private function monthEnd(string $period): CarbonImmutable
    {
        return $this->monthStart($period)->endOfMonth()->startOfDay();
    }
}
