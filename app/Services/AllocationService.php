<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\ApplicationStatus;
use App\Exceptions\HallException;
use App\Models\Allocation;
use App\Models\Seat;
use App\Models\SeatApplication;
use App\Models\User;
use App\Notifications\HallAlert;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Seat allocation rules.
 *
 *  1. A seat can have only one active resident.
 *  2. A student can hold only one seat at a time.
 *  3. Rooms under maintenance cannot receive anyone.
 *
 * Rules 1 and 2 are checked here AND enforced by the unique columns
 * allocations.active_seat_id / active_user_id, so two admins clicking at the
 * same moment can never put two students on one seat.
 */
class AllocationService
{
    public function allocate(
        User $student,
        Seat $seat,
        User $by,
        ?CarbonInterface $startDate = null,
        ?SeatApplication $application = null,
    ): Allocation {
        $start = CarbonImmutable::parse($startDate ?? today())->startOfDay();

        $allocation = DB::transaction(function () use ($student, $seat, $by, $start, $application) {
            $seat = Seat::query()->with('room')->lockForUpdate()->findOrFail($seat->id);

            if (! $student->isStudent() || ! $student->is_active) {
                throw new HallException('Only an active student account can get a seat.');
            }
            if (! $seat->room->isAvailable()) {
                throw new HallException("Room {$seat->room->number} is under maintenance, choose another room.");
            }
            if (Allocation::query()->active()->where('seat_id', $seat->id)->exists()) {
                throw new HallException("Seat {$seat->code()} is already taken.");
            }
            if (Allocation::query()->active()->where('user_id', $student->id)->exists()) {
                throw new HallException("{$student->name} already has a seat. Use Transfer to move the student.");
            }

            // A direct allocation also settles the student's pending application, if any
            $application ??= SeatApplication::query()
                ->where('user_id', $student->id)
                ->where('status', ApplicationStatus::Pending)
                ->latest()
                ->first();

            try {
                $allocation = Allocation::query()->create([
                    'user_id' => $student->id,
                    'seat_id' => $seat->id,
                    'application_id' => $application?->id,
                    'start_date' => $start,
                    'status' => AllocationStatus::Active,
                    'allocated_by' => $by->id,
                    'active_seat_id' => $seat->id,
                    'active_user_id' => $student->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new HallException('Someone else just allocated this seat or this student. Refresh the page and try again.');
            }

            if ($application) {
                $application->update([
                    'status' => ApplicationStatus::Approved,
                    'reviewed_by' => $by->id,
                    'reviewed_at' => now(),
                ]);
            }

            // Any other open application of this student is no longer needed
            SeatApplication::query()
                ->where('user_id', $student->id)
                ->where('status', ApplicationStatus::Pending)
                ->update(['status' => ApplicationStatus::Cancelled]);

            return $allocation;
        });

        $allocation->load('seat.room');
        $student->notify(new HallAlert(
            'Seat allocated',
            "You have been given seat {$allocation->seat->code()} on {$allocation->seat->room->floorLabel()}, from {$start->format('j M Y')}.",
            route('student.room'),
            'success',
        ));

        return $allocation;
    }

    /**
     * Move a resident to another seat. The old allocation ends the day before
     * $date and a new one starts on $date, so billing stays correct.
     */
    public function transfer(Allocation $current, Seat $newSeat, User $by, ?CarbonInterface $date = null): Allocation
    {
        $date = CarbonImmutable::parse($date ?? today())->startOfDay();

        $allocation = DB::transaction(function () use ($current, $newSeat, $by, $date) {
            $current = Allocation::query()->with('seat.room')->lockForUpdate()->findOrFail($current->id);
            $newSeat = Seat::query()->with('room')->lockForUpdate()->findOrFail($newSeat->id);

            if (! $current->isActive()) {
                throw new HallException('This allocation has already ended.');
            }
            if ($current->seat_id === $newSeat->id) {
                throw new HallException('The student is already on this seat.');
            }
            if (! $newSeat->room->isAvailable()) {
                throw new HallException("Room {$newSeat->room->number} is under maintenance.");
            }
            if (Allocation::query()->active()->where('seat_id', $newSeat->id)->exists()) {
                throw new HallException("Seat {$newSeat->code()} is already taken.");
            }
            if ($date->lt($current->start_date)) {
                throw new HallException('The transfer date cannot be before the current allocation started.');
            }

            // Moved on the first day: treat it as a correction of the same allocation
            if ($date->equalTo($current->start_date)) {
                $current->update(['seat_id' => $newSeat->id, 'active_seat_id' => $newSeat->id]);

                return $current;
            }

            $current->update([
                'status' => AllocationStatus::Ended,
                'end_date' => $date->subDay(),
                'end_reason' => "Transferred to seat {$newSeat->code()}",
                'ended_by' => $by->id,
                'active_seat_id' => null,
                'active_user_id' => null,
            ]);

            return Allocation::query()->create([
                'user_id' => $current->user_id,
                'seat_id' => $newSeat->id,
                'start_date' => $date,
                'status' => AllocationStatus::Active,
                'allocated_by' => $by->id,
                'active_seat_id' => $newSeat->id,
                'active_user_id' => $current->user_id,
            ]);
        });

        $allocation->load('seat.room', 'user');
        $allocation->user->notify(new HallAlert(
            'Seat changed',
            "You have been moved to seat {$allocation->seat->code()} from {$date->format('j M Y')}.",
            route('student.room'),
            'info',
        ));

        return $allocation;
    }

    /**
     * End a stay. $lastDay is the last night the student stayed in the hall.
     */
    public function vacate(Allocation $allocation, User $by, ?CarbonInterface $lastDay = null, ?string $reason = null): void
    {
        $lastDay = CarbonImmutable::parse($lastDay ?? today())->startOfDay();

        DB::transaction(function () use ($allocation, $by, $lastDay, $reason) {
            $allocation = Allocation::query()->lockForUpdate()->findOrFail($allocation->id);

            if (! $allocation->isActive()) {
                throw new HallException('This allocation has already ended.');
            }
            if ($lastDay->lt($allocation->start_date)) {
                throw new HallException('The last day cannot be before the allocation started.');
            }

            $allocation->update([
                'status' => AllocationStatus::Ended,
                'end_date' => $lastDay,
                'end_reason' => $reason ?: 'Seat vacated',
                'ended_by' => $by->id,
                'active_seat_id' => null,
                'active_user_id' => null,
            ]);
        });

        $allocation->user->notify(new HallAlert(
            'Seat vacated',
            "Your hall seat was released with {$lastDay->format('j M Y')} as the last day. Please clear any dues at the hall office.",
            route('student.bills.index'),
            'warning',
        ));
    }
}
