<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AllocationStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Ended => 'Ended',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Ended => 'gray',
        };
    }
}
