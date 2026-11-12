<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoomStatus: string
{
    use HasOptions;

    case Available = 'available';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Maintenance => 'Under maintenance',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Maintenance => 'amber',
        };
    }
}
