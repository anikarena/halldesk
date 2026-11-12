<?php

namespace Tests\Concerns;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;

/**
 * Small helpers to set up rooms and people in a test.
 */
trait BuildsHall
{
    protected function room(string $number = '101', int $capacity = 2, int $rent = 3000, RoomStatus $status = RoomStatus::Available): Room
    {
        $room = Room::query()->create([
            'number' => $number,
            'floor' => (int) substr($number, 0, 1),
            'capacity' => $capacity,
            'monthly_rent' => $rent,
            'status' => $status,
        ]);
        foreach (Room::seatLabels($capacity) as $label) {
            $room->seats()->create(['label' => $label]);
        }

        return $room->load('seats');
    }

    protected function seat(Room $room, string $label = 'A'): Seat
    {
        return $room->seats->firstWhere('label', $label);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function staffMember(): User
    {
        return User::factory()->staff()->create();
    }

    protected function student(): User
    {
        return User::factory()->student()->create();
    }
}
