<?php

namespace App\Services\Assistant\Tools;

use App\Models\Allocation;
use App\Models\User;
use App\Services\Assistant\Tool;

class MyRoom extends Tool
{
    public function name(): string
    {
        return 'my_room';
    }

    public function description(): string
    {
        return 'The student\'s seat, room, floor, monthly rent and electricity charge, and roommates. For a student without a seat, the status of their seat application.';
    }

    public function allows(User $user): bool
    {
        return $user->isStudent();
    }

    public function run(User $user, array $args): array
    {
        $allocation = $user->activeAllocation()->with('seat.room')->first();

        if (! $allocation) {
            $application = $user->applications()->first();

            return [
                'has_seat' => false,
                'application' => $application ? [
                    'status' => $application->status->label(),
                    'submitted' => $application->created_at->format('D, j M Y'),
                    'decision_note' => $application->decision_note,
                ] : null,
                '_links' => [['label' => 'Apply for a seat', 'url' => route('student.apply')]],
            ];
        }

        $room = $allocation->seat->room;
        $roommates = Allocation::query()->active()
            ->whereHas('seat', fn ($q) => $q->where('room_id', $room->id))
            ->where('user_id', '!=', $user->id)
            ->with('user', 'seat')
            ->get()
            ->map(fn (Allocation $a) => ['name' => $a->user->name, 'seat' => $a->seat->label])
            ->all();

        return [
            'has_seat' => true,
            'room' => $room->number,
            'seat' => $allocation->seat->label,
            'floor' => $room->floorLabel(),
            'room_type' => $room->typeLabel(),
            'monthly_rent' => taka($room->monthly_rent),
            'electricity_per_month' => taka((int) setting('electricity_charge')),
            'total_per_month_before_meals' => taka($room->monthly_rent + (int) setting('electricity_charge')),
            'since' => $allocation->start_date->format('D, j M Y'),
            'roommates' => $roommates,
            '_links' => [['label' => 'My room', 'url' => route('student.room')]],
        ];
    }
}
