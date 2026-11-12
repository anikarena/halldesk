<?php

namespace App\Services\Assistant\Tools;

use App\Models\Complaint;
use App\Models\User;
use App\Services\Assistant\Tool;

class OpenComplaints extends Tool
{
    public function name(): string
    {
        return 'open_complaints';
    }

    public function description(): string
    {
        return 'Complaints that are not resolved yet, high priority and oldest first, with room, student and who is handling them.';
    }

    public function allows(User $user): bool
    {
        return $user->isManager();
    }

    public function run(User $user, array $args): array
    {
        $query = Complaint::query()->unresolved();

        return [
            'count' => (clone $query)->count(),
            'complaints' => $query->with('user', 'room', 'assignee')
                ->orderByRaw("case priority when 'high' then 3 when 'medium' then 2 else 1 end desc")
                ->oldest()
                ->limit(15)
                ->get()
                ->map(fn (Complaint $complaint) => [
                    'reference' => $complaint->reference(),
                    'title' => $complaint->title,
                    'room' => $complaint->room?->number,
                    'student' => $complaint->user->name,
                    'priority' => $complaint->priority->label(),
                    'status' => $complaint->status->label(),
                    'waiting_since' => $complaint->created_at->diffForHumans(),
                    'handled_by' => $complaint->assignee?->name,
                ])->all(),
            '_links' => [['label' => 'Complaints', 'url' => route('manage.complaints.index')]],
        ];
    }
}
