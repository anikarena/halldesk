<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Assistant\Tool;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DuesList extends Tool
{
    public function name(): string
    {
        return 'dues_list';
    }

    public function description(): string
    {
        return 'Students who owe money, largest amount first, with their room and oldest due date.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'overdue_only' => ['type' => 'boolean', 'description' => 'Only bills past their due date. Default false.'],
            ],
        ];
    }

    public function allows(User $user): bool
    {
        return $user->isManager();
    }

    public function run(User $user, array $args): array
    {
        $students = self::owing((bool) ($args['overdue_only'] ?? false));

        return [
            'students' => $students->count(),
            'total' => taka($students->sum('due')),
            'list' => $students->take(15)->map(fn (array $row) => [
                'name' => $row['user']->name,
                'student_id' => $row['user']->profile?->student_id,
                'room' => $row['user']->activeAllocation?->seat->room->number,
                'due' => taka($row['due']),
                'oldest_due_date' => $row['oldest']->format('j M Y'),
                'overdue' => $row['overdue'],
            ])->values()->all(),
            '_links' => [['label' => 'Bills', 'url' => route('manage.invoices.index')]],
        ];
    }

    /**
     * Everyone with an unpaid balance, largest first. Shared with RemindDues.
     *
     * @return Collection<int, array{user: User, due: int, oldest: CarbonInterface, overdue: bool}>
     */
    public static function owing(bool $overdueOnly): Collection
    {
        return Invoice::query()
            ->when($overdueOnly, fn ($q) => $q->overdue(), fn ($q) => $q->outstanding())
            ->with('user.profile', 'user.activeAllocation.seat.room')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $invoices) => [
                'user' => $invoices->first()->user,
                'due' => $invoices->sum(fn (Invoice $invoice) => $invoice->balance()),
                'oldest' => $invoices->min('due_date'),
                'overdue' => $invoices->contains(fn (Invoice $invoice) => $invoice->isOverdue()),
            ])
            ->filter(fn (array $row) => $row['due'] > 0)
            ->sortByDesc('due')
            ->values();
    }
}
