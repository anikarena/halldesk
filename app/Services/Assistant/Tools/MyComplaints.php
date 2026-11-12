<?php

namespace App\Services\Assistant\Tools;

use App\Models\Complaint;
use App\Models\User;
use App\Services\Assistant\Tool;

class MyComplaints extends Tool
{
    public function name(): string
    {
        return 'my_complaints';
    }

    public function description(): string
    {
        return 'The student\'s recent complaints with their status, priority, who is handling them and the latest update.';
    }

    public function allows(User $user): bool
    {
        return $user->isStudent();
    }

    public function run(User $user, array $args): array
    {
        $complaints = $user->complaints()
            ->with(['assignee', 'updates' => fn ($q) => $q->latest()])
            ->latest()
            ->limit(8)
            ->get();

        return [
            'complaints' => $complaints->map(function (Complaint $complaint) {
                $update = $complaint->updates->first();

                return [
                    'reference' => $complaint->reference(),
                    'title' => $complaint->title,
                    'category' => $complaint->category->label(),
                    'priority' => $complaint->priority->label(),
                    'status' => $complaint->status->label(),
                    'submitted' => $complaint->created_at->format('D, j M Y'),
                    'handled_by' => $complaint->assignee?->name,
                    'latest_update' => $update ? trim(($update->to_status ? 'Status changed to '.$update->to_status->label().'. ' : '').($update->body ?? '')).' ('.$update->created_at->format('j M').')' : null,
                ];
            })->all(),
            '_links' => [['label' => 'My complaints', 'url' => route('student.complaints.index')]],
        ];
    }
}
