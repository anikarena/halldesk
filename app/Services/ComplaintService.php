<?php

namespace App\Services;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Exceptions\HallException;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\HallAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Complaint workflow: open -> in progress -> resolved.
 * Every comment and status change is saved in complaint_updates, which is
 * shown as a timeline to both the student and the staff.
 */
class ComplaintService
{
    /**
     * @param  array{category: string, title: string, description: string, priority: string}  $data
     */
    public function create(User $student, array $data): Complaint
    {
        $allocation = $student->activeAllocation()->with('seat')->first();
        if (! $allocation) {
            throw new HallException('Only residents can submit complaints.');
        }

        $complaint = Complaint::query()->create([
            ...$data,
            'user_id' => $student->id,
            'room_id' => $allocation->seat->room_id,
            'status' => ComplaintStatus::Open,
        ]);

        if ($complaint->priority === ComplaintPriority::High) {
            Notification::send(User::query()->managers()->where('is_active', true)->get(), new HallAlert(
                'High priority complaint',
                "{$complaint->reference()} {$complaint->title} (room {$complaint->room?->number})",
                route('manage.complaints.show', $complaint),
                'danger',
            ));
        }

        return $complaint;
    }

    public function comment(Complaint $complaint, User $author, string $body): void
    {
        $complaint->updates()->create(['user_id' => $author->id, 'body' => $body]);

        if ($author->isStudent() && $complaint->assignee) {
            $complaint->assignee->notify(new HallAlert(
                'New comment on '.$complaint->reference(),
                $complaint->title,
                route('manage.complaints.show', $complaint),
            ));
        } elseif ($author->isManager()) {
            $complaint->user->notify(new HallAlert(
                'Reply on your complaint',
                "{$complaint->reference()} {$complaint->title}",
                route('student.complaints.show', $complaint),
            ));
        }
    }

    /**
     * Staff change status, priority or the assigned person, with an optional note.
     */
    public function manage(Complaint $complaint, User $staff, ComplaintStatus $status, ComplaintPriority $priority, ?int $assignedTo, ?string $note): void
    {
        DB::transaction(function () use ($complaint, $staff, $status, $priority, $assignedTo, $note) {
            $from = $complaint->status;
            $previousAssignee = $complaint->assigned_to;

            $complaint->update([
                'status' => $status,
                'priority' => $priority,
                'assigned_to' => $assignedTo,
                'resolved_at' => $status === ComplaintStatus::Resolved ? ($complaint->resolved_at ?? now()) : null,
            ]);

            if ($from !== $status || filled($note)) {
                $complaint->updates()->create([
                    'user_id' => $staff->id,
                    'body' => $note,
                    'from_status' => $from !== $status ? $from : null,
                    'to_status' => $from !== $status ? $status : null,
                ]);
            }

            if ($from !== $status) {
                $complaint->user->notify(new HallAlert(
                    "Complaint {$status->label()}",
                    "{$complaint->reference()} {$complaint->title}",
                    route('student.complaints.show', $complaint),
                    $status === ComplaintStatus::Resolved ? 'success' : 'info',
                ));
            }

            if ($assignedTo && $assignedTo !== $previousAssignee && $assignedTo !== $staff->id) {
                $complaint->assignee()->first()?->notify(new HallAlert(
                    'Complaint assigned to you',
                    "{$complaint->reference()} {$complaint->title}",
                    route('manage.complaints.show', $complaint),
                    'warning',
                ));
            }
        });
    }

    /**
     * The student says the problem is not fixed.
     */
    public function reopen(Complaint $complaint, User $student, string $reason): void
    {
        if ($complaint->status !== ComplaintStatus::Resolved) {
            throw new HallException('Only a resolved complaint can be reopened.');
        }

        $complaint->update(['status' => ComplaintStatus::Open, 'resolved_at' => null]);
        $complaint->updates()->create([
            'user_id' => $student->id,
            'body' => $reason,
            'from_status' => ComplaintStatus::Resolved,
            'to_status' => ComplaintStatus::Open,
        ]);

        $complaint->assignee?->notify(new HallAlert(
            'Complaint reopened',
            "{$complaint->reference()} {$complaint->title}",
            route('manage.complaints.show', $complaint),
            'warning',
        ));
    }
}
