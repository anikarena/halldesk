<?php

namespace App\Services\Assistant\Tools;

use App\Models\Seat;
use App\Models\User;
use App\Services\Assistant\Tool;

class VacantSeats extends Tool
{
    public function name(): string
    {
        return 'vacant_seats';
    }

    public function description(): string
    {
        return 'Seats that nobody holds right now, in rooms that are not under maintenance, with room type, floor and rent.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'floor' => ['type' => 'integer', 'description' => 'Only this floor number. Leave out for all floors.'],
            ],
        ];
    }

    public function allows(User $user): bool
    {
        return $user->isManager();
    }

    public function run(User $user, array $args): array
    {
        $seats = Seat::query()->vacant()->with('room')
            ->when(isset($args['floor']), fn ($q) => $q->whereHas('room', fn ($r) => $r->where('floor', (int) $args['floor'])))
            ->get()
            ->sortBy(fn (Seat $seat) => $seat->code(), SORT_NATURAL);

        return [
            'count' => $seats->count(),
            'seats' => $seats->take(40)->map(fn (Seat $seat) => [
                'seat' => $seat->code(),
                'floor' => $seat->room->floorLabel(),
                'room_type' => $seat->room->typeLabel(),
                'rent' => taka($seat->room->monthly_rent),
            ])->values()->all(),
            '_links' => [['label' => 'Rooms and seats', 'url' => route('manage.rooms.index')]],
        ];
    }
}
