<?php

namespace App\Services\Assistant\Tools;

use App\Exceptions\HallException;
use App\Models\User;
use App\Notifications\HallAlert;
use App\Services\Assistant\Tool;

class RemindDues extends Tool
{
    public function name(): string
    {
        return 'remind_dues';
    }

    public function description(): string
    {
        return 'Send a bill reminder notification to every student who owes money (or only those with overdue bills). '
            .'Each student sees their own amount. The office user confirms on screen before anything is sent.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'overdue_only' => ['type' => 'boolean', 'description' => 'Only students with overdue bills. Default true.'],
            ],
        ];
    }

    public function allows(User $user): bool
    {
        return $user->isManager();
    }

    public function changesData(): bool
    {
        return true;
    }

    public function describe(User $user, array $args): string
    {
        $overdueOnly = (bool) ($args['overdue_only'] ?? true);
        $students = DuesList::owing($overdueOnly);
        if ($students->isEmpty()) {
            throw new HallException($overdueOnly ? 'No student has an overdue bill.' : 'No student owes money.');
        }

        return 'Send a bill reminder to '.$students->count().' '.str('student')->plural($students->count())
            .($overdueOnly ? ' with overdue bills' : ' who owe money').' ('.taka($students->sum('due')).' in total)';
    }

    public function run(User $user, array $args): array
    {
        $students = DuesList::owing((bool) ($args['overdue_only'] ?? true));

        foreach ($students as $row) {
            $row['user']->notify(new HallAlert(
                'Bill reminder',
                'You have '.taka($row['due']).' to pay'.($row['overdue'] ? ', and a bill is past its due date' : '').'. Please pay it from Bills and payments.',
                route('student.bills.index'),
                'warning',
            ));
        }

        return ['done' => true, 'reminders_sent' => $students->count(), 'total_due' => taka($students->sum('due'))];
    }
}
